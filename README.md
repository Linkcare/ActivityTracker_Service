# ActivityTracker Service
WS-API Service for Activity Tracker programs

Contents of the project:

Web site
========
This project contains only 2 views used for:
- auth_request.php: Redirects to the Fitbit Oauth authorization page
- auth_response.php: Receiver of the Fitbit authorization information

SOAP services
=============
update_activity(): Function to connect to Fitbit and retrieve the activity data (steps) of a patient