# SynchWeb
SynchWeb is an ISPyB web application which consists of a PHP REST API and a Backbone/Marionette javascript client.
The client includes some newer components written in Vue.js

Read More: https://diamondlightsource.github.io/SynchWeb/

## Installation
Running SynchWeb requires setting up a Linux, Apache, MariaDB and PHP (LAMP) software stack. If running in production you should configure your Apache and PHP to serve secure pages only. The steps below describe how to build the software so it is ready to deploy onto your target server. 
The [podman](./podman) folder provides support for creating a containerised 
production deployment. Full instructions [here](./podman/README.md).

For development, a simple environment can be setup by using scripts provided [here](https://github.com/DiamondLightSource/synchweb-devel-env). They are not intended for production use but include scripts to automatically build and deploy the software on a local VM or in a Podman container.

### Requirements
If not using the Podman containter, to build SynchWeb on a machine you will need the following on the build machine:

- [npm](https://docs.npmjs.com/) 
- [composer](https://getcomposer.org/)
- appropriate version of PHP on the build machine

If not using the development VMs you will also need an instance of the
ISPyB database - available
[here](https://github.com/DiamondLightSource/ispyb-database).

### Check out the code
```sh
$ git clone https://github.com/DiamondLightSource/SynchWeb
```

### Customise front end - config.json
An example configuration is provided in client/src/js/config_sample.json
This file should be copied to create a client/src/js/config.json file and edited to customise the application for your site.

| Parameter | Description |
| ------ | ------ |
| apiurl | Base API root path of back end services e.g. /api |
| appurl | Base API root path of client app, normally not required ""|
| production | deprecated with webpack build |
| build | git hash of current build, used if dynamic reload of pages required post deployment|
| pucks | Array that lists default number of puck positions in sample changers for beamlines |
| gsMajorAxisOrientation | Determines whether the major grid scan axis determines the orientation of the view |
| maintenance_message | Can be used so app serves static page under maintenance periods |
| maintenance | Flag to indicate if client is in maintenance mode|
| ga_ident | Google Analytics id|
| site_name | Site Name to display in footer |
| site_link | URL to site home page |
| data_catalogue | Object that includes name and url property for a link to a data catalogue - displayed on the landing page |

Site Image can be customised via the tailwind.config.js header-site-logo and footer-site-logo values.

### Build front end
See package.json for the full list of commands that can be run.

```sh
$ cd SynchWeb/client
$ npm install
$ npm run build
```

### Customise back end - config.php
An example configuration is provided in api/config_sample.php
Main items to change include:
- database connection parameters (user, password, host, port)
- authentication type (cas, ldap, dummy/no authentication)

### Build back end
```sh
$ cd SynchWeb/api
$ composer install
```

Note, the front and backend are built automatically in the Podman deployment.

### Run backend tests
Tests are available for the PHP code under `api/tests`.  To run these, go to the `api` directory and use:

```sh
$ cd SynchWeb/api
$ ./vendor/bin/phpunit --verbose tests
```
Note, a single test can be run by specifying that instead of the `tests` directory.

### Run front end tests for Vue.js
Testing on the front end is restricted to the newer Vue.js code as it is 
anticipated that the older code will eventually be migrated to this form.
To run these tests, 

```sh
$ cd SynchWeb/client
$ npm run test
```

### Developing the client application
It is possible to run the client code on a local machine and connect to an existing SynchWeb installation on a server.
The steps required are to build the front end code and then run a webpack dev server to host the client code.  Note, this is possible for both the
Podman and VM approach detailed [here](https://github.com/DiamondLightSource/synchweb-devel-env).
```sh
$ cd SynchWeb/client
$ npm run build:dev
$ npm run serve -- --env port=8080 --env.proxy.target=http://localhost:8082
```
In this example a browser pointed at localhost:9000 will connect to a SynchWeb back end on localhost:8082. Don't ignore the middle '--' otherwise the dev server will not receive the arguments!

The command line options available are described in this table. These override the defaults set in webpack.config.js.

| Parameter | Description |
| ------ | ------ |
| env.port | Webpack dev server port |
| env.proxy.target | Full address of the SynchWeb PHP backend server (can include port if required) |
| env.proxy.secure | Flag to set if connecting to an https address for the SynchWeb backend.  Setting to `false` can also help with self-signed SSL certs (which may be insecure so should not be used in production). |

Acknowledgements
----------------
If you make use of code from this repository, please reference:
Fisher et al., J. Appl. Cryst. (2015). 48, 927-932, doi:10.1107/S1600576715004847
https://journals.iucr.org/j/issues/2015/03/00/fs5101/index.html
