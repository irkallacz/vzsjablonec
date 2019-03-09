# VZS Jablonec Web Project

VZS Jablonec has unique web architecture. Front web is static Wordpress installation, but there are bunch subdomains witch are endpoints for one big Nette framework application, where each subdomain is one module. In organization, Google Apps are widely used, so app make good use of that too. 

More at https://member.vzs-jablonec.cz

## Features

 - Users management
 - Events management 
 - Forum
 - Surveys
 - Photo gallery
 - Single Sign-On (SSO)
 	- Sign via Google or Facebook account
 - Integration of Google App service (via cron jobs) 
 	- Calendar
 	- Contacts
 	- Drive
 
## Requirements

 - PHP 7.0
 - Nette 2.4
 - MySql/MariaDB
 - Image Magic PHP extention

More at [composer.json](composer.json)

Uses Bitbucket [pipelines](bitbucket-pipelines.yml) for deployment.
 
 ## Testing
 
App is covered by tests (find in [tests directory](tests)). Testbench and Tester packages are used for testing purposed.
 
To run a tests just execute [bin\tester](bin/tester).