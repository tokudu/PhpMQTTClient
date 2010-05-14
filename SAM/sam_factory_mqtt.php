<?php
/*
+----------------------------------------------------------------------+
| Copyright IBM Corporation 2006, 2007.                                      |
| All Rights Reserved.                                                 |
+----------------------------------------------------------------------+
|                                                                      |
| Licensed under the Apache License, Version 2.0 (the "License"); you  |
| may not use this file except in compliance with the License. You may |
| obtain a copy of the License at                                      |
| http://www.apache.org/licenses/LICENSE-2.0                           |
|                                                                      |
| Unless required by applicable law or agreed to in writing, software  |
| distributed under the License is distributed on an "AS IS" BASIS,    |
| WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or      |
| implied. See the License for the specific language governing         |
| permissions and limitations under the License.                       |
+----------------------------------------------------------------------+
| Author: Dave Renshaw                                                 |
+----------------------------------------------------------------------+

$Id: sam_factory_mqtt.php,v 1.1 2007/02/02 15:40:41 dsr Exp $

*/
require_once('MQTT/sam_mqtt.php');
return new SAMConnection_MQTT();
?>