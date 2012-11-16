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

$Id: sam_mqtt.php,v 1.1 2007/02/02 15:36:46 dsr Exp $

*/

define("SAM_MQTT_CLEANSTART", "SAM_MQTT_CLEANSTART");
define("SAM_MQTT_QOS", "SAM_MQTT_QOS");
define("SAM_MQTT_SUB_SEPARATOR", "#-#");
/* ---------------------------------
    SAMConnection
   --------------------------------- */

class SAMConnection_MQTT {

  var $debug = false;

  var $errno = 0;
  var $error = '';

  /*
   Info we need to keep between calls...
  */
  var $sub_id = '';
  var $port = '';
  var $host = '';
  var $cleanstart = false;
  var $virtualConnected = false;
  var $connected = false;
  /*
   Our current open socket...
  */
  var $sock;

  /*
   Table of available operations using the MQTT protocol...
  */
  var $operations = array("MQTT_CONNECT"     => 1,
                          "MQTT_CONNACK"     => 2,
                          "MQTT_PUBLISH"     => 3,
                          "MQTT_PUBACK"      => 4,
                          "MQTT_PUBREC"      => 5,
                          "MQTT_PUBREL"      => 6,
                          "MQTT_PUBCOMP"     => 7,
                          "MQTT_SUBSCRIBE"   => 8,
                          "MQTT_SUBACK"      => 9,
                          "MQTT_UNSUBSCRIBE" => 10,
                          "MQTT_UNSUBACK"    => 11,
                          "MQTT_PINGREC"     => 12,
                          "MQTT_PINGRESP"    => 13,
                          "MQTT_DISCONNECT"  => 14);

  /* ---------------------------------
      Constructor
     --------------------------------- */
  function SAMConnection_MQTT() {
    if ($this->debug) e('SAMConnection_MQTT()');

    if ($this->debug) x('SAMConnection_MQTT()');
  }

  /* ---------------------------------
      Commit
     --------------------------------- */
  function Commit() {
    if ($this->debug) e('SAMConnection_MQTT.Commit()');

    $errno = 100;
    $error = 'Unsupported operation for MQTT protocol!';
    $rc = false;

    if ($this->debug) x("SAMConnection_MQTT.Commit() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Connect
     --------------------------------- */
  function Connect($proto, $options=array()) {
    if ($this->debug) e('SAMConnection_MQTT.Connect()');

    /* Check our optional parameter array for the necessary bits...   */
    if ($options[SAM_PORT] == '') {
        $this->port = 1883;
    } else {
        $this->port = $options[SAM_PORT];
    }
    if ($options[SAM_HOST] == '') {
        $this->host = 'localhost';
    } else {
        $this->host = $options[SAM_HOST];
    }

    $this->cleanstart = in_array(SAM_MQTT_CLEANSTART, $options);

    if ($this->debug) t("SAMConnection_MQTT.Connect() host=$this->host, port=$this->port, cleanstart=$this->cleanstart");

    if ($this->checkHost($this->host, $this->port)) {
        $this->virtualConnected = true;
    } else {
        $this->virtualConnected = false;
    }

    if ($this->debug) x("SAMConnection_MQTT.Connect() rc=$this->virtualConnected");
    return $this->virtualConnected;
  }

  /* ---------------------------------
      Disconnect
     --------------------------------- */
  function Disconnect() {
    if ($this->debug) e('SAMConnection_MQTT.Disconnect()');
    $rc = false;

    if ($this->virtualConnected) {
        if ($this->connected) {
            $msg = $this->fixed_header("MQTT_DISCONNECT").pack('C', 0);
            fwrite($this->sock, $msg);
            $response = fgets($this->sock, 128);
            if ($this->debug) t('SAMConnection_MQTT.Disconnect() response is '.strlen($response).' bytes');
            if (strlen($response) == 0) {
                fclose($this->sock);
                $this->sock = NULL;
            }
        }
        $this->virtualConnected = false;
        $this->connected = false;
        $rc = true;
    }

    if ($this->debug) x("SAMConnection_MQTT.Disconnect() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      IsConnected
     --------------------------------- */
  function IsConnected() {
    if ($this->debug) e('SAMConnection_MQTT.IsConnected()');
    $rc = false;

    if ($this->connected) {
        $rc = true;
    }

    if ($this->debug) x("SAMConnection_MQTT.IsConnected() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Peek
     --------------------------------- */
  function Peek() {
    if ($this->debug) e('SAMConnection_MQTT.Peek()');

    $errno = 100;
    $error = 'Unsupported operation for MQTT protocol!';
    $rc = false;

    if ($this->debug) x("SAMConnection_MQTT.Peek() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      PeekAll
     --------------------------------- */
  function PeekAll() {
    if ($this->debug) e('SAMConnection_MQTT.PeekAll()');

    $errno = 100;
    $error = 'Unsupported operation for MQTT protocol!';
    $rc = false;

    if ($this->debug) x("SAMConnection_MQTT.PeekAll() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Receive
     --------------------------------- */
  function Receive($sub_id, $options=array()) {
    if ($this->debug) e('SAMConnection_MQTT.Receive()');
    $rc = false;

    /* strip the topic from the rear of the subscription id...  */
    $x = strpos($sub_id, SAM_MQTT_SUB_SEPARATOR);
    if (!$x) {
        $this->errno = 279;
        $this->error = 'Specified subscription id ('.$sub_id.') is not valid!';
        return false;
    }
    $topic = substr($sub_id, $x + strlen(SAM_MQTT_SUB_SEPARATOR));
    $si = substr($sub_id, 0, $x);

    /* Are we already connected?               */
    if (!$this->connected) {
        if ($this->debug) t('SAMConnection_MQTT.Receive() Not connected.');
        /* No, so open up the connection...    */
        $this->sub_id = $si;
        $rc = $this->do_connect_now();
    } else {
        /* We are already connected. Are we using the right subscriber id?  */
        if ($this->sub_id != $si) {
            if ($this->debug) t('SAMConnection_MQTT.Receive() Connected with wrong sub_id.');
            /* No, We better reconnect then...  */
            $this->disconnect();
            $this->sub_id = $si;
            $rc = $this->do_connect_now();
        } else {
            if ($this->debug) t('SAMConnection_MQTT.Receive() Connected OK.');
            $rc = true;
        }
    }

    if ($rc) {

        /* have we got a timeout specified?    */
        if ($options[SAM_WAIT] > 1) {
            $m = $options[SAM_WAIT] % 1000;
            $s = ($options[SAM_WAIT] - $m) /1000;
            if ($this->debug) t('SAMConnection_MQTT.Receive() timeout='.$options[SAM_WAIT]." ($s secs $m millisecs)");
            stream_set_timeout($this->sock, $s, $m);
            if ($this->debug) t('SAMConnection_MQTT.Receive() timeout set.');
        } else {
            if ($this->debug) t('SAMConnection_MQTT.Receive() no timeout value found!');
        }

        $hdr = $this->read_fixed_header($this->sock);
        if (!$hdr) {
            $this->errno = 500;
            $this->error = 'Receive request failed, timed out with no data!';
            $rc = false;
        } else {
            if ($hdr['mtype'] == $this->operations['MQTT_PUBLISH']) {
                $len = $this->read_remaining_length($this->sock);
                if ($len > 1) {
                    /* read the topic length...   */
                    $topic = $this->read_topic($this->sock);
                    if (!$topic) {
                        $this->errno = 303;
                        $this->error = 'Receive request failed, message format invalid!';
                        $rc = false;
                    } else {
                        if ($this->debug) t('SAMConnection_MQTT.Receive() topic='.$topic);
                        $len -= (strlen($topic) + 2);
                        /* If QoS 1 or 2 then read the message id...   */
                        if ($hdr['qos'] > 0) {
                            $idb = fread($this->sock, 2);
                            $len -= 2;
                            $fields = unpack('na', $idb);
                            $mid = $fields['a'];
                            if ($this->debug) t('SAMConnection_MQTT.Receive() mid='.$mid);
                        }
                        $payload = fread($this->sock, $len);
                        if ($this->debug) t('SAMConnection_MQTT.Receive() payload='.$payload);
                        $rc = new SAMMessage();
                        $rc->body = $payload;
                        $rc->header->SAM_MQTT_TOPIC = 'topic://'.$topic;
                        $rc->header->SAM_MQTT_QOS = $hdr['qos'];
                        $rc->header->SAM_TYPE = 'SAM_BYTES';
                    }
                } else {
                    $this->errno = 303;
                    $this->error = 'Receive request failed, received message too short! No topic data';
                    $rc = false;
                }
            } else {
                if ($this->debug) t('SAMConnection_MQTT.Receive() Receive failed response mtype = '.$mtype);
                $rc = false;
            }
        }
    }

    if ($this->debug) x("SAMConnection_MQTT.Receive() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Remove
     --------------------------------- */
  function Remove() {
    if ($this->debug) e('SAMConnection_MQTT.Remove()');

    $errno = 100;
    $error = 'Unsupported operation for MQTT protocol!';
    $rc = false;

    if ($this->debug) x("SAMConnection_MQTT.Remove() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Rollback
     --------------------------------- */
  function Rollback() {
    if ($this->debug) e('SAMConnection_MQTT.Rollback()');

    $errno = 100;
    $error = 'Unsupported operation for MQTT protocol!';
    $rc = false;

    if ($this->debug) x("SAMConnection_MQTT.Rollback() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Send
     --------------------------------- */
  function Send($topic, $message, $options=array()) {
    if ($this->debug) e('SAMConnection_MQTT.Send()');
    $rc = true;

    /* check the format of the topic...   */
    if (strncmp($topic, 'topic://', 8) == 0) {
        $t = substr($topic, 8);
    } else {
        $this->errno = 279;
        $this->error = 'Specified target ('.$topic.') is not a valid topic!';
        return false;
    }

    if (array_key_exists(SAM_MQTT_QOS, $options)) {
        $qos = $options[SAM_MQTT_QOS];
    } else {
        $qos = 0;
    }

    /* Are we already connected?               */
    if (!$this->connected) {
        /* No, so open up the connection...    */
        $this->do_connect_now();
    }

    $mid = rand();
    $variable = $this->utf($t);
    if ($qos > 0) {
        $variable .= pack('n', $mid);
    }

    $payload = $message->body;

    // add in the remaining length field and fix it together
    $msg = $this->fixed_header("MQTT_PUBLISH", 0, $qos) . $this->remaining_length(strlen($variable)+strlen($payload)) . $variable . $payload;

    fwrite($this->sock, $msg);
    if ($qos > 0) {
        $hdr = $this->read_fixed_header($this->sock);
        if ($hdr) {
            /* is this a QoS level 1 message being sent?      */
            if ($qos == 1) {
                /* Yup, so we should get a PUBACK response message...    */
                if ($hdr['mtype'] == $this->operations['MQTT_PUBACK']) {
                    $len = $this->read_remaining_length($this->sock);
                    if ($len > 0) {
                        $response = fread($this->sock, $len);
                    }
                    if ($len < 2) {
                        if ($this->debug) t("SAMConnection_MQTT.Send() send failed, incorrect length response ($len) received!");
                        $this->errno = 302;
                        $this->error = 'Send request failed!';
                        $rc = false;
                    } else {
                        $rc = true;
                    }
                } else {
                    if ($this->debug) t('SAMConnection_MQTT.Send() Send failed response mtype = '.$mtype.' Expected PUBREC!');
                    $rc = false;
                }
            } else {
                /* lets assume it's QoS level 2...               */
                /* We should get a PUBREC response message...    */
                if ($hdr['mtype'] == $this->operations['MQTT_PUBREC']) {
                    $len = $this->read_remaining_length($this->sock);
                    if ($len > 0) {
                        $response = fread($this->sock, $len);
                    }
                    if ($len < 2) {
                        if ($this->debug) t("SAMConnection_MQTT.Send() send failed, incorrect length response ($len) received!");
                        $this->errno = 302;
                        $this->error = 'Send request failed!';
                        $rc = false;
                    } else {
                        $rc = true;
                        /* Now we can send a PUBREL message...       */
                        $variable = pack('n', $mid);
                        $msg = $this->fixed_header("MQTT_PUBREL").$this->remaining_length(strlen($variable)).$variable;
                        fwrite($this->sock, $msg);

                        /* get a response...                         */
                        $hdr = $this->read_fixed_header($this->sock);
                        if ($hdr['mtype'] == $this->operations['MQTT_PUBCOMP']) {
                            $len = $this->read_remaining_length($this->sock);
                            if ($len > 0) {
                                $response = fread($this->sock, $len);
                            }
                            if ($len < 2) {
                                if ($this->debug) t("SAMConnection_MQTT.Send() send failed, incorrect length response ($len) received!");
                                $this->errno = 302;
                                $this->error = 'Send request failed!';
                                $rc = false;
                            } else {
                                $rc = true;
                            }
                        } else {
                            if ($this->debug) t('SAMConnection_MQTT.Send() Send failed response mtype = '.$mtype.' Expected PUBCOMP!');
                            $rc = false;
                        }
                    }
                } else {
                    if ($this->debug) t('SAMConnection_MQTT.Send() Send failed response mtype = '.$mtype);
                    $rc = false;
                }
            }
        }
    }

    if ($this->debug) x("SAMConnection_MQTT.Send() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      SetDebug
     --------------------------------- */
  function SetDebug($option=false) {
    $this->debug = $option;
    return;
  }

  /* ---------------------------------
      Subscribe
     --------------------------------- */
  function Subscribe($topic, $options=array()) {
    if ($this->debug) e("SAMConnection_MQTT.Subscribe($topic)");
    $rc = true;

    /* check the format of the topic...   */
    if (strncmp($topic, 'topic://', 8) == 0) {
        $t = substr($topic, 8);
    } else {
        $this->errno = 279;
        $this->error = 'Specified target ('.$topic.') is not a valid topic!';
        return false;
    }

    if (in_array(SAM_MQTT_QOS, $options)) {
        $qos = $options[SAM_MQTT_QOS];
    } else {
        $qos = 0;
    }

    /* Are we already connected?               */
    if (!$this->connected) {
        /* No, so open up the connection...    */
        if (!$this->do_connect_now()) {
            return false;
        }
    }

    // variable header: message id (16 bits)
    $x = rand(1, 16000);
    $variable = pack('n', $x);

    // payload: client ID
    $payload = $this->utf($t).pack('C', $qos);

    // add in the remaining length field and fix it together
    $msg = $this->fixed_header("MQTT_SUBSCRIBE", 0, 1) . $this->remaining_length(strlen($variable)+strlen($payload)) . $variable . $payload;

    fwrite($this->sock, $msg);
    $hdr = $this->read_fixed_header($this->sock);
    if (!$hdr) {
        if ($this->debug) t("SAMConnection_MQTT.Subscribe() subscribe failed, no response from broker!");
        $this->errno = 301;
        $this->error = 'Subscribe request failed, no response from broker!';
        $rc = false;
    } else {
        if ($hdr['mtype'] == $this->operations['MQTT_SUBACK']) {
            $len = $this->read_remaining_length($this->sock);
            if ($len > 0) {
                $response = fread($this->sock, $len);
                /* Return the subscription id with the topic appended to it so we can unsubscribe easily... */
                $rc = $this->sub_id.SAM_MQTT_SUB_SEPARATOR.$t;
            }
            if ($len < 3) {
                if ($this->debug) t("SAMConnection_MQTT.Subscribe() subscribe failed, incorrect length response ($len) received!");
                $this->errno = 301;
                $this->error = 'Subscribe request failed, incorrect length response ($len) received!';
                $rc = false;
            }
        } else {
            if ($this->debug) t('SAMConnection_MQTT.Subscribe() subscribe failed response mtype = '.$mtype);
            $rc = false;
        }
    }

    if ($this->debug) x("SAMConnection_MQTT.Subscribe() rc=$rc");
    return $rc;
  }

  /* ---------------------------------
      Unsubscribe
     --------------------------------- */
  function Unsubscribe($sub_id) {
    if ($this->debug) e("SAMConnection_MQTT.Unsubscribe($sub_id)");

    /* Detach the topic from the rear of the subscription id...   */
    $x = strpos($sub_id, SAM_MQTT_SUB_SEPARATOR);
    if (!$x) {
        $this->errno = 279;
        $this->error = 'Specified subscription id ('.$sub_id.') is not valid!';
        return false;
    }

    $topic = substr($sub_id, $x + strlen(SAM_MQTT_SUB_SEPARATOR));
    $si = substr($sub_id, 0, $x);


    /* Are we already connected?               */
    if (!$this->connected) {
        if ($this->debug) t('SAMConnection_MQTT.Unsubscribe() Not connected.');
        /* No, so open up the connection...    */
        $this->sub_id = $si;
        $rc = $this->do_connect_now();
    } else {
        /* We are already connected. Are we using the right subscriber id?  */
        if ($this->sub_id != $si) {
            if ($this->debug) t('SAMConnection_MQTT.Unsubscribe() Connected with wrong sub_id.');
            /* No, We better reconnect then...  */
            $this->disconnect();
            $this->sub_id = $si;
            $rc = $this->do_connect_now();
        } else {
            if ($this->debug) t('SAMConnection_MQTT.Unsubscribe() Connected OK.');
            $rc = true;
        }
    }

    /* variable header: message id (16 bits)  */
    $x = rand(1, 16000);
    $variable = pack('n', $x);

    /* payload: client ID    */
    $payload = $this->utf($topic);

    /* add in the remaining length field and fix it together   */
    $msg = $this->fixed_header("MQTT_UNSUBSCRIBE", 0, 1) . $this->remaining_length(strlen($variable)+strlen($payload)) . $variable . $payload;

    fwrite($this->sock, $msg);
    $hdr = $this->read_fixed_header($this->sock);
    if (!$hdr) {
        if ($this->debug) t("SAMConnection_MQTT.Unsubscribe() unsubscribe failed, no response from broker!");
        $this->errno = 302;
        $this->error = 'Unsubscribe request failed, no response from broker!';
        $rc = false;
    } else {
        if ($hdr['mtype'] == $this->operations['MQTT_UNSUBACK']) {
            $len = $this->read_remaining_length($this->sock);
            if ($len > 0) {
                $response = fread($this->sock, $len);
                $rc = $this->sub_id;
            }
            if ($len != 2) {
                if ($this->debug) t("SAMConnection_MQTT.Unsubscribe() unsubscribe failed, incorrect length response ($len) received!");
                $this->errno = 301;
                $this->error = "Unsubscribe request failed, incorrect length response ($len) received!";
                $rc = false;
            }
        } else {
            if ($this->debug) t('SAMConnection_MQTT.Unsubscribe() unsubscribe failed response mtype = '.$hdr['mtype']);
            $rc = false;
        }
    }

    if ($this->debug) x("SAMConnection_MQTT.Unsubscribe() rc=$rc");
    return $rc;
  }



  function remaining_length($l) {
    /* return the remaining length field bytes for an integer input parameter   */
    if ($this->debug) t("SAMConnection_MQTT.remaining_length() l=$l");

    $rlf = '';
    do {
      $digit = $l % 128;
      $l = ($l - $digit)/128;
      if ($this->debug) t("SAMConnection_MQTT.remaining_length() digit=$digit l=$l");

      # if there are more digits to encode, set the top bit of this digit
      if ( $l > 0 ) {
        $digit += 128;
      }
      $digit = pack('C', $digit);

      $rlf .= $digit;
      if ($this->debug) t("SAMConnection_MQTT.remaining_length() rlf=$rlf");
    } while ($l > 0);

    return $rlf;
  }

  function utf($s) {
    /* return the UTF-8 encoded version of the parameter    */
    $l = strlen($s);
    $b1 = pack('C', $l/256);
    $b2 = pack('C', $l%256);
    $rc = $b1.$b2.$s;
    return $rc;
  }

  function fixed_header($operation, $dup=0, $qos=0, $retain=0) {
    /* fixed header: msg type (4) dup (1) qos (2) retain (1)   */
    return pack('C', ($this->operations[$operation] * 16) + ($dup * 4) + ($qos * 2) + $retain);
  }

  function checkHost($hostname, $port) {
      if ($this->debug) e("SAMConnection_MQTT.checkHost($hostname)");
      $rc = false;

      $fp = fsockopen($hostname, $port);
      if (!$fp) {
          $rc = false;
      } else {
          $this->sock = $fp;
          $rc = true;
      }
      if ($this->debug) x("SAMConnection_MQTT.checkHost(rc=$rc)");
      return $rc;
  }

  function do_connect_now() {
    $rc = true;

    /* Do we have a client/subscriber id yet?       */
    if ($this->sub_id == '') {
        /* No, so create a unique one...            */
        $this->sub_id = uniqid('', true);
        if ($this->debug) t("SAMConnection_MQTT.do_connect_now() sub_id=$this->sub_id");
    } else {
        if ($this->debug) t("SAMConnection_MQTT.do_connect_now() using existing sub_id=$this->sub_id");
    }

    if ($this->cleanstart) {
        $x = "\x03";
    } else {
        $x = "\x00";
    }
    $variable = $this->utf('MQIsdp')."\x03$x\x00\x00";

    /* payload is subscriber id                 */
    $payload = $this->utf($this->sub_id);

    /* add in the remaining length field and fix it together   */
    $msg = $this->fixed_header("MQTT_CONNECT") . $this->remaining_length(strlen($variable)+strlen($payload)) . $variable . $payload;

    $errno = 0;
    $errstr = '';

    if (!$this->virtualConnected) {
        $fp = fsockopen($this->host, $this->port, $errno, $errstr);
        if (!$fp) {
            if ($this->debug) t("SAMConnection_MQTT.do_connect_now() fsockopen failed! ($errno) $errstr");
            $this->errno = 208;
            $this->error = 'Unable to open socket to broker!';
            $this->sock = NULL;
            return false;
        } else {
            $this->virtualConnected = true;
            $this->sock = $fp;
        }
    }

    stream_set_timeout($this->sock, 10);
    fwrite($this->sock, $msg);

    $hdr = $this->read_fixed_header($this->sock);
    if ($hdr) {
        if ($hdr['mtype'] == $this->operations['MQTT_CONNACK']) {
            $len = $this->read_remaining_length($this->sock);
            if ($len < 2) {
                if ($this->debug) t("SAMConnection_MQTT.do_connect_now() connect failed, incorrect length response ($len) received!");
                $this->errno = 218;
                $this->error = 'Unable to open connection to broker!';
                $rc = false;
            } else {
                $response = fread($this->sock, $len);
                $fields = unpack('Ccomp/Cretcode', $response);
                if ($fields['retcode'] == 0) {
                    $rc = $this->sock;
                    $this->connected = true;
                    $rc = true;
                    if ($this->debug) t('SAMConnection_MQTT.do_connect_now() connected OK');
                } else {
                    if ($this->debug) t('SAMConnection_MQTT.do_connect_now() connect failed retcode = '.$fields['retcode']);
                    $rc = false;
                    if ($fields['retcode'] == 2) {
                        $this->sub_id = '';
                        $this->errno = 279;
                        $this->error = 'Invalid subscription id!';
                    }
                }
            }
        } else {
            if ($this->debug) t('SAMConnection_MQTT.do_connect_now() connect failed response mtype = '.$mtype);
            $rc = false;
        }
    }

    if (!$rc) {
        fclose($this->sock);
        $this->sock = NULL;
        $this->virtualConnected = false;
    }

    return $rc;
  }

  function read_fixed_header($conn) {
      $rc = false;
      $response = fread($conn, 1);
      if (strlen($response) > 0) {
          $fields = unpack('Cbyte1', $response);
          $x = $fields['byte1'];
          $ret = $x % 2;
          $x -= $ret;
          $qos = ($x % 8) / 2;
          $x -= ($qos * 2);
          $dup = ($x % 16) / 8;
          $x -= ($dup * 8);
          $mtype = $x / 16;
          if ($this->debug) t("SAMConnection_MQTT.read_fixed_header() mtype=$mtype, dup=$dup, qos=$qos, retain=$ret");
          $rc = array('mtype' => $mtype, 'dup' => $dup, 'qos' => $qos, 'retain' => $ret);
      }
      return $rc;
  }

  function read_remaining_length($conn) {
      $rc = 0;
      $m = 1;
      while (!feof($conn)) {
          $byte = fgetc($conn);
          $fields = unpack('Ca', $byte);
          $x = $fields['a'];
          if ($this->debug) t('SAMConnection_MQTT.read_remaining_length() byte ('.strlen($byte).') = '.$x);
          if ($x < 128) {
              $rc += $x * $m;
              break;
          } else {
              $rc += (($x - 128) * $m);
          }
          $m *= 128;
      }
      if ($this->debug) t('SAMConnection_MQTT.read_remaining_length() remaining length = '.$rc);
      return $rc;
  }

  function read_topic($conn) {
      if ($this->debug) e('SAMConnection_MQTT.read_topic()');
      $rc = false;
      while (!feof($conn)) {
          $tlen = fread($conn, 2);
          $fields = unpack('na', $tlen);
          if ($this->debug) t('SAMConnection_MQTT.read_topic() topic length='.$fields['a']);
          $rc = fread($conn, $fields['a']);
          break;
      }
      if ($this->debug) x("SAMConnection_MQTT.read_topic(rc=$rc)");
      return $rc;
  }
}

?>

