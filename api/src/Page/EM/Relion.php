<?php

namespace SynchWeb\Page\EM;

use SynchWeb\Page\EM\ArgumentValidator;
use SynchWeb\Page\EM\RelionParameterTransformer;
use SynchWeb\Page\EM\RelionSchema;

trait Relion
{
    /**
     * Create a data collection and a processing job then enqueue the job
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function relionStart()
    {
        global $visit_directory, $zocalo_mx_reprocess_queue;

        $this->exitIfElectronMicroscopesAreNotConfigured();
        $session = $this->sessionFetch($this->arg('session'));

        /* TODO Temporary override to make session available for testing
           after session has ended (JPH) */
        // $this->sessionExitIfNotActive($session);

        $sessionPath = $this->sessionSubstituteValuesInPath($session, $visit_directory);

        $argumentValidator = new ArgumentValidator(RelionSchema::schema());
        $invalid = $argumentValidator->validateArguments($this->args);

        if (count($invalid) > 0) {
            error_log('Invalid parameters: ' . var_export($invalid, true));
            $this->_error($invalid, 400);
        }

        $transformer = new RelionParameterTransformer($sessionPath);
        $workflowParameters = $transformer->postParameters($this->args);

        /* Create a ProcessingJob with ProcessingJobParameters
           for Zocalo to trigger RELION processing.
           This requires a DataCollection
           which in turn requires a DataCollectionGroup. */

        $dataCollectionId = $this->findExistingDataCollection(
            $session['sessionId'],
            $transformer->getImageDirectory(),
            $transformer->getFileTemplate()
        );

        if (!$dataCollectionId) {
            $dataCollectionId = $this->addDataCollectionForEM(
                $session,
                $transformer->getImageDirectory(),
                $this->args['import_images_ext'],
                $transformer->getFileTemplate()
            );
        }

        $processingJobId = $dataCollectionId ?
            $this->addProcessingJob($dataCollectionId, $workflowParameters) :
            null;

        // Send job to processing queue
        $message = array(
            'parameters' => array('ispyb_process' => $processingJobId)
        );

        $this->enqueue($zocalo_mx_reprocess_queue, $message);

        // TODO Remove temporary output of message and workflow_parameters

        $output = array(
            'timestamp' => gmdate('c', time()),
            'message' => $message
        );

        error_log(var_export($output, true));
        $this->_output($output);
    }

    public function relionStatus()
    {
        // global $visit_directory;

        $this->exitIfElectronMicroscopesAreNotConfigured();
        $session = $this->sessionFetch($this->arg('session'));

        // TODO RESTORE FOR PRODUCTION
        // $this->sessionExitIfNotActive($session);

        $output = array(
            'timestamp' => gmdate('c', time()),
            'processingIsActive' => false, // $session['processingIsActive'],
            'processingTimestamp' => $session['processingTimestamp'] ?
                gmdate('c', $session['processingTimestamp']) :
                null
        );

        $this->_output($output);
    }

    public function relionStop()
    {
        global $zocalo_mx_reprocess_queue;

        // $session = $this->determineSession($this->arg('session'));
        // $this->exitIfSessionIsNotActive($session);

        // Finds queued and running ProcessingJobs associated with session
        // Returns null otherwise

        if ($this->arg('processingJobId')) {
            $result = $this->db->pq("
                SELECT processingJobId, dataCollectionId
                FROM ProcessingJob
                WHERE processingJobId = :1", array($this->arg('processingJobId')));

            if (count($result)) {
                $message = array(
                    'parameters' => array(
                        'ispyb_process' => $result[0]['PROCESSINGJOBID']
                    ),
                    'recipes' => ['relion-stop']
                );

                $this->enqueue($zocalo_mx_reprocess_queue, $message);
                return;
            }

            $message = 'Processing job not found!';
            error_log($message);
            $this->_error($message, 400);
        }

        $output = array(
            'timestamp' => gmdate('c', time()),
            'message' => $message
        );

        $this->_output($output);
    }

    public function relionParameters()
    {
        if (!$this->has_arg('processingJobId')) {
            $this->_error('Processing Job ID not provided');
        }

        $parameters = $this->db->pq("
            SELECT pj.processingjobparameterid,
                pj.processingjobid,
                pj.parameterkey,
                pj.parametervalue
            FROM ProcessingJobParameter pj
            WHERE pj.processingjobid = :1", array($this->arg('processingJobId')));

        if (!sizeof($parameters)) {
            $this->_error('No parameters for processing job');
        }

        $this->_output(array('data' => $parameters, 'total' => sizeof($parameters)));
    }

    ////////////////////////////////////////////////////////////////////////////

    private function findExistingDataCollection(
        $session,
        $imageDirectory,
        $fileTemplate
    ) {
        // Returns dataCollectionId of first DataCollection associated with session
        // Also checks for existing imageDirectory
        // Returns null otherwise

        if ($session['SESSIONID']) {
            $result = $this->db->pq(
                "SELECT dataCollectionId FROM DataCollection
                    WHERE SESSIONID = :1 AND imageDirectory = :2 AND fileTemplate = :3
                    LIMIT 1",
                array(
                    $session['SESSIONID'],
                    $imageDirectory,
                    $fileTemplate
                )
            );
            if (count($result)) {
                return $result[0]['DATACOLLECTIONID'];
            }
        }

        return null;
    }

    private function exitIfUnfinishedProcessingJobsExist($session)
    {
        // Finds queued and running ProcessingJobs associated with session
        // Returns null otherwise

        if ($session['SESSIONID']) {
            $result = $this->db->pq(
                "SELECT
                    APP.autoProcProgramId,
                    APP.processingStartTime,
                    APP.processingJobId,
                    CASE
                        WHEN (processingStartTime IS NULL AND processingEndTime IS NULL AND processingStatus IS NULL) THEN 'queued'
                        WHEN (processingStartTime IS NOT NULL AND processingEndTime IS NULL AND processingStatus IS NULL) THEN 'running'
                    END AS processingStatusDescription
                FROM AutoProcProgram APP
                JOIN ProcessingJob PJ ON PJ.processingJobId = APP.processingJobId
                JOIN DataCollection DC ON PJ.dataCollectionId = DC.dataCollectionId
                JOIN BLSession BLS ON DC.SESSIONID = BLS.sessionId
                WHERE processingStatus IS NULL
                  AND BLS.sessionId = :1",
                array($session['SESSIONID'])
            );

            if (count($result)) {
                $message = 'Relion processing job already exists for this session!';

                error_log($message);
                $this->_error($message, 400);

                return $result;
            }
        }

        return null;
    }

    private function addDataCollectionForEM($session, $imageDirectory, $imageSuffix, $fileTemplate)
    {
        $dataCollectionId = null;

        try {
            $this->db->start_transaction();

            // Add DataCollectionGroup

            $this->db->pq(
                "INSERT INTO DataCollectionGroup (sessionId, comments, experimentType)
                VALUES (:1, :2, :3) RETURNING dataCollectionGroupId INTO :id",
                array($session['SESSIONID'], 'Created by SynchWeb', 'EM')
            );

            $dataCollectionGroupId = $this->db->id();

            // Add DataCollection

            $this->db->pq(
                "INSERT INTO DataCollection (sessionId, dataCollectionGroupId, startTime,
                    endTime, runStatus, imageDirectory, imageSuffix, fileTemplate, comments)
                VALUES (:1, :2, NOW(), :3, :4, :5, :6, :7, :8) RETURNING dataCollectionId INTO :id",
                array(
                    $session['SESSIONID'],
                    $dataCollectionGroupId,
                    $session['ENDDATE'],
                    'DataCollection Simulated',
                    $imageDirectory,
                    $imageSuffix,
                    $fileTemplate,
                    'Created by SynchWeb'
                )
            );

            $dataCollectionId = $this->db->id();

            $this->db->end_transaction();
        } catch (Exception $e) {
            error_log("Failed to add DataCollection to database.");
            $this->_error("Failed to add DataCollection to database.", 500);
        }

        return $dataCollectionId;
    }

    private function addProcessingJob($dataCollectionId, $workflowParameters)
    {
        $processingJobId = null;

        try {
            $this->db->start_transaction();

            // Add ProcessingJob

            $this->db->pq(
                "INSERT INTO ProcessingJob (
                    dataCollectionId,
                    displayName,
                    comments,
                    recipe,
                    automatic
                )
                VALUES (:1, :2, :3, :4, :5)
                RETURNING processingJobId INTO :id",
                array(
                    $dataCollectionId,
                    'RELION',
                    'Submitted via SynchWeb',
                    'relion',
                    0
                )
            );
            $processingJobId = $this->db->id();

            foreach ($workflowParameters as $key => $value) {
                $this->db->pq(
                    "INSERT INTO ProcessingJobParameter (
                        processingJobId,
                        parameterKey,
                        parameterValue
                    )
                    VALUES (:1, :2, :3)",
                    array(
                        $processingJobId,
                        $key,
                        (is_bool($value) ? var_export($value, true) : $value)
                    )
                );
            }

            $this->db->end_transaction();
        } catch (Exception $e) {
            error_log("Failed to add ProcessingJob to database.");
            $this->_error("Failed to add ProcessingJob to database.", 500);
        }

        return $processingJobId;
    }
}
