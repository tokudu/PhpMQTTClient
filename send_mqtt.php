<?php

require('SAM/php_sam.php');

//create a new connection object
$conn = new SAMConnection();

//start initialise the connection
$conn->connect(SAM_MQTT, array(SAM_HOST => '127.0.0.1',
                               SAM_PORT => 1883));      
//create a new MQTT message with the output of the shell command as the body
$msgCpu = new SAMMessage($_REQUEST['message']);

//send the message on the topic cpu
$conn->send('topic://'.$_REQUEST['target'], $msgCpu);
         
$conn->disconnect();         

echo 'MQTT Message to ' . $_REQUEST['target'] . ' sent: ' . $_REQUEST['message']; 

?>
