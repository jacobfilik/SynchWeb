<?php

namespace SynchWeb\Page;

use SynchWeb\Page;

class Pod extends Page 
{
    public static $arg_list = array('id' => '\d+',
                                    'user' => '.*',
                                    'app' => '.*',
                            );

    public static $dispatch = array(array('/:id', 'get', '_initiate_pod'),
                                    array('/running/:id', 'get', '_pod_running'),
                                    array('/status/:id', 'get', '_pod_start_status'),
                                    array('/kill', 'post', '_kill_pod')
                        );

    /**
     * Compile necessary parameters and send curl request to launcher application to start up a new pod
     */
    function _initiate_pod(){
        $person = $this->_get_person();
        $app = $this->arg('app');

        // Currently we only allow users to spin up one H5Web pod and we can't map a new file into existing Pod
        $personHasPod = $this->db->pq("SELECT podId FROM Pod WHERE status IS NOT NULL AND status !=:1 AND STATUS !=:2 AND personId =:3 AND app =:4", array('Terminated', 'Failed', $person, $app));
        if(sizeof($personHasPod) > 0) $this->_error('You have an existing instance of ' . $app . ' running.');

        $filePath = $this->_get_file_path();
        $path = $filePath['FILEPATH'];
        $file = $filePath['FILENAME'];

        // Insert row acknowledging a valid pod request was sent to SynchWeb
        // Need to update the Pod table app enum field to allow h5web and jnb (jupyter notebook)
        $this->db->pq("INSERT INTO Pod (podid, app, status, personid, filePath) 
                    VALUES (s_pod.nextval, :1, :2, :3, :4)",
                    array($app, 'Requested', $person, $path.$file));
        $podId = $this->db->id();

        $data = array(
            'user' => $this->arg('user'),
            'path' => $path,
            'file' => $file,
            'podid' => $podId,
            'app' => $app,
        );

        global $h5web_service_url, $h5web_service_cert;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $h5web_service_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $h5web_service_cert);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Blocks echo of curl response
        $result = curl_exec($ch);
        curl_close($ch);

        $this->_output(array('podId' => $podId));
    }

    /**
     * SynchWeb polls this method to check Pod status during Pod startup
     * Could be deprecated if we decide to wait for curl requests to complete (since initiate_pod will always succeed or fail)
     */
    function _pod_start_status() {
        $podId = $this->arg('id');
        $row = $this->db->pq("SELECT status, ip, message, app FROM Pod where podId=:1", array($podId));
        $this->_output($row);
    }

    /**
     * SynchWeb polls this method to check if a Pod has terminated
     */
    function _pod_running() {
        if(!$this->has_arg('app')) $this->_error('No app parameter provided!');

        $person = $this->_get_person();

        $filePath = $this->_get_file_path();
        $path = $filePath['FILEPATH'];
        $file = $filePath['FILENAME'];

        $row = $this->db->pq("SELECT ip, app, message, filePath FROM Pod WHERE filePath LIKE CONCAT(:1, '%') AND personId = :2 AND status = :3 AND app = :4 ORDER BY created DESC LIMIT 1",
                            array($path, $person, 'Running', $this->arg('app')));

        // Imperfect solution as it doesn't account for the visit number (app.vist on client side is broken)
        if(strpos($path, $this->arg('prop')) === false) {
            $this->_output();
        } else {
            $this->_output($row);
        }
    }

    /**
     * Look up which JNB pod has been requested for termination and send request to service launcher to initiate
     * Only for JNB pods for now, H5Web pods will self terminate once all browsers referencing the pod are closed
     */
    function _kill_pod() {

        if(!$this->has_arg('user') || !$this->has_arg('app')) $this->_error('No user or app provided. Invalid kill request');

        $row = $this->db->pq("SELECT podId FROM Pod WHERE personId = :1 AND app = :2 AND status = :3", array($this->_get_person(), $this->arg('app'), 'Running'));
        $podId = $row[0]['PODID'];
        
        $data = array(
            'user' => $this->arg('user'),
            'podid' => $podId,
            'app' => $this->arg('app')
        );

        global $h5web_service_url, $h5web_service_cert;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $h5web_service_url . "/kill");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $h5web_service_cert);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Blocks echo of curl response
        $result = curl_exec($ch);
        curl_close($ch);

        $this->_output(array('podId' => $podId));
    }

    /**
     * Helper method to get person who owns a Pod
     * Used as a query parameter to track Pod status
     */
    function _get_person() {
        if(!$this->has_arg('user')) $this->_error('No User Provided!');

        $row = $this->db->pq("SELECT personId FROM Person WHERE login = :1", array($this->arg('user')));
        if(!sizeof($row)) $this->error('No such user');

        return $row[0]['PERSONID'];
    }

    function _get_file_path() {
        switch($this->arg('app')){
            case "H5Web":
                return $this->_get_dc_file_path();
                break;
            case "JNB":
                return $this->_get_autoproc_attachment_file_path();
                break;
            default:
                $this->_error('invalid app provided');
        }
    }

    /**
     * Helper method to get file path associated with a data collection
     * The path & filename is passed into a pod or used as another query parameter to check a Pod status
     */
    function _get_dc_file_path() {
        if(!$this->has_arg('id')) $this->_error('No DCID Provided!');

        $row = $this->db->pq("SELECT imageDirectory as FILEPATH, fileTemplate as FILENAME FROM datacollection WHERE datacollectionid =:1", array($this->arg('id')));
        $item = $row[0];
        $path = $item['FILEPATH'];
        $file = $item['FILENAME'];

        if(!file_exists($path.$file)) $this->_error('File does not exist at the provided location!');

        return $item;
    }

    /**
     * Helper method to get file path associated with an auto processing attachment
     * The path & filename is passed into a pod or used as another query parameter to check a Pod status
     */
    function _get_autoproc_attachment_file_path() {
        if(!$this->has_arg('id')) $this->_error('No APPAID Provided!');

        $row = $this->db->pq("SELECT filePath, fileName FROM AutoProcProgramAttachment WHERE autoProcProgramAttachmentId =:1", array($this->arg('id')));
        $item = $row[0];
        $path = $item['FILEPATH'] . "/";
        $file = $item['FILENAME'];

        if(!file_exists($path.$file)) $this->_error('File does not exist at the provided location!');

        return $item;
    }
}

?>