<?php

/*
 * ILUKA
 *
 * Copyright (C) 2006,2007 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

/***********************************************************
 * �С�����륵����������� 
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/
include("../initial");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibiluka");

/********************************************************
�ƥڡ����������
*********************************************************/

define("OPERATION", "Configuration virtual_server");
define("TMPLFILE", "iluka_lb_vsconf.tmpl");

/********************************************************
�����Х��ѿ�
*********************************************************/

/* ɽ���Ѥ�ʬ�����르�ꥺ���ž������ */
$algo_list = array('�饦��ɥ�ӥ� (rr)',
                   '�Ť��դ��饦��ɥ�ӥ� (wrr)',
                   '�Ǿ���³ (lc)',
                   '�Ť��դ��Ǿ���³ (wlc)',
                   '��³���١����Ǿ���³ (lblc)',
                   '��³���ϥå��� (sh)',
                   '��³��ϥå��� (dh)');

$kind_list = array('�ͥåȥ�����ɥ쥹�� (NAT)',
                   '�����쥯�ȥ롼�ƥ��� (DR)',
                   '�ȥ�ͥ�� (TUN)');

$protocol_list = array('TCP', 'UDP');
$protocol_name = array('tcp', 'udp');

/* ����ե������Ѥ�ʬ�����르�ꥺ���ž������ */
$check_lb_algo = array("rr", "wrr", "lc", "wlc", "lblc", "sh", "dh");
$check_lb_kind = array("NAT", "DR", "TUN"); 
$check_protocol = array("TCP", "UDP"); 

/*********************************************************
 * get_virtual_conf
 *
 * [IP_PORT].conf����Ϥ���
 *
 * [����]
 *       $vsconfpass          [IP_PORT].conf�Υѥ�
 *       &$data               [IP_PORT].conf������ 
 *       $post                �ݥ��Ȥ���Ƥ�����
 *
 * [�֤���]
 *       TRUE                 ����
 *       FALSE                �۾�  
 **********************************************************/
function get_virtual_conf ($vsconfpass, &$data, $post)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $check_lb_algo;
    global $check_lb_kind;
    global $check_protocol;

    $data["virtualhost"]            = "";
    $data["sorry_server_ipaddress"] = "";
    $data["sorry_server_port"]      = "";
   
    $head = "virtual_server ".$post["ipaddress"]." ".$post["port"]." {";

    $fh = fopen($vsconfpass, "r");
    if ($fh === FALSE) {
        $err_msg = sprintf($msgarr['28045'][SCREEN_MSG], $vsconfpass);
        $log_msg = sprintf($msgarr['28045'][LOG_MSG], $vsconfpass);
        return FALSE;
    }


    while($result = fgets($fh)) {
        $result = trim($result);
        if ($result === $head) {
            break;
        }
    }
    if ($result === FALSE) {
        $err_msg = sprintf($msgarr['28073'][SCREEN_MSG], $vsconfpass);
        $log_msg = sprintf($msgarr['28073'][LOG_MSG], $vsconfpass);
        return FALSE;
    }

    while($result = fgets($fh)) {
        $result = trim($result);
        $lines = preg_split("[\s+]", $result, 2);

        if ($lines[0] === "}") {
            break;
        }

        /* �ƻ�ֳ֡�delay_loop�ˤμ��� */
        if ($lines[0] === "delay_loop") {
            if ($lines[0] === $result
            || strlen($lines[1]) > 10 
            || ctype_digit($lines[1]) === FALSE) {

                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�ƻ�ֳ�", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "delay_loop", $vsconfpass);
                return FALSE;
            }
            $data["delay_loop"] = $lines[1];
        }

        /* �ץ�ȥ����protcol�ˤμ��� */
        if ($lines[0] === "protocol") {
            if ($lines[0] === $result) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�ץ�ȥ���", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "protocol", $vsconfpass);
                return FALSE;
            }
            $protocol_number = array_search($lines[1], $check_protocol);
            if ($protocol_number === FALSE) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�ץ�ȥ���", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "protocol", $vsconfpass);
                return FALSE;
            }
            $data["protocol"] = $protocol_number;
        }

        /* ʬ�����르�ꥺ���lb_algo�ˤμ��� */
        if ($lines[0] === "lb_algo") {
            if ($lines[0] === $result) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "ʬ�����르�ꥺ��", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "lb_algo", $vsconfpass);
                return FALSE;
            }
            $algo_number = array_search($lines[1], $check_lb_algo);
            if ($algo_number === FALSE) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "ʬ�����르�ꥺ��", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "lb_algo", $vsconfpass);
                return FALSE;
            }
            $data["lb_algo"] = $algo_number;
        }

        /* ž����ˡ��lb_kind�ˤμ��� */
        if ($lines[0] === "lb_kind") {
            if ($lines[0] === $result) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "ž����ˡ", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "lb_kind", $vsconfpass);
                return FALSE;
            }
            $kind_number = array_search($lines[1], $check_lb_kind);
            if ($kind_number === FALSE) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "ž����ˡ", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "lb_kind", $vsconfpass);
                return FALSE;
            }
            $data["lb_kind"] = $kind_number;
        }

        /* �����ॢ���ȡ�persistence_timeout�ˤμ��� */
        if ($lines[0] === "persistence_timeout") {
            if ($lines[0] === $result 
            || strlen($lines[1]) > 5 
            || ctype_digit($lines[1]) === FALSE 
            || $lines[1] > 65535) {

                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�����ॢ����", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "persistence_timeout", $vsconfpass);
                return FALSE;
            }
            $data["persistence_timeout"] = $lines[1];
        }

        /* �С������ۥ��ȡ�virtualhost�ˤμ��� */
        if ($lines[0] === "virtualhost") {
            if ($lines[0] === $result || strlen($lines[1]) > 256) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�С������ۥ���", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "virtualhost", $vsconfpass);
                return FALSE;
            }
            $num = "0123456789";
            $sl = "abcdefghijklmnopqrstuvwxyz";
            $ll = strtoupper($sl);
            $sym = "!#$%&'*+-/=?^_{}~.";
            $allow_letter = $num . $sl . $ll . $sym;
            if (strspn($lines[1], $allow_letter) != strlen($lines[1])) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "�С������ۥ���", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "virtualhost", $vsconfpass);
                return FALSE;
            }
            $data["virtualhost"] = $lines[1];
        }

        /* Sorry�����С�sorry_server�ˤμ��� */
        if ($lines[0] === "sorry_server") {
            if ($lines[0] === $result) {
                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "Sorry������", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "Sorry_server", $vsconfpass);
                return FALSE;
            }

            $sorry_server = preg_split("[\s+]", $lines[1], 2);

            /* sorry_server_ipaddress�ڤ�sorry_server_port�η��������å� */
            if (strlen($sorry_server[0]) > 39 
            || !filter_var($sorry_server[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) 
            || $sorry_server[0] === $lines[1]
            || strlen($sorry_server[1]) > 5 
            || ctype_digit($sorry_server[1]) === FALSE 
            || $sorry_server[1] > 65535) {

                $err_msg = sprintf($msgarr['28072'][SCREEN_MSG], "Sorry������", $vsconfpass);
                $log_msg = sprintf($msgarr['28072'][LOG_MSG], "Sorry_server", $vsconfpass);
                return FALSE;
            }
            $data["sorry_server_ipaddress"] = $sorry_server[0];
            $data["sorry_server_port"] = $sorry_server[1];
        }
    }
    if ($result === FALSE) {
        $err_msg = sprintf($msgarr['28053'][SCREEN_MSG], $vsconfpass);
        $log_msg = sprintf($msgarr['28053'][LOG_MSG], $vsconfpass);
        return FALSE;
    }

    return TRUE;
}



/***********************************************************
 * change_conffile
 *
 * [IP_PORT].conf����Ȥ��ѹ�
 *
 * [����]
 *        $tmplfile           virtual_server.conf.tmpl�Υѥ�
 *        $post               ���Ϥ��줿��
 *        $iport              [IP_PORT]
 * [�֤���]
 *        ̵�� 
 **********************************************************/
function change_conffile ($tmplfile, $post, $iport)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $check_lb_algo;
    global $check_lb_kind;
    global $check_protocol;

    $dirs   = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/";
    $vsfile = "$dirs$iport".'.conf';
    $vsdir  = "$dirs$iport";
    $sorry  = $post["sorry_server_ipaddress"].' '.$post["sorry_server_port"];

    /* tmpl�ե������tag���ִ� */
    $conftmpl = file_get_contents($tmplfile);
    if ($conftmpl === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    $tag["<<IP_ADDRESS>>"]          = $post["ipaddress"];
    $tag["<<PORT>>"]                = $post["port"];
    $tag["<<PROTOCOL_NUMBER>>"]     = $post["protocol"];

    $tag["<<DELAY_LOOP>>"]          = "delay_loop ".$post["delay_loop"];
    $tag["<<LB_ALGO>>"]             = "lb_algo ".$check_lb_algo[$post["lb_algo"]];
    $tag["<<LB_KIND>>"]             = "lb_kind ".$check_lb_kind[$post["lb_kind"]];
    $tag["<<PERSISTENCE TIMEOUT>>"] = "persistence_timeout ".$post["persistence_timeout"];
    $tag["<<PROTOCOL>>"]            = "protocol ".$check_protocol[$post["protocol"]];

    if ($post["virtualhost"] === "") {
        $tag["<<VIRTUALHOST>>"] = "";
    } else {
        $tag["<<VIRTUALHOST>>"] = 'virtualhost '.$post["virtualhost"];
    }

    if ($post["sorry_server_ipaddress"] == "") {
        $tag["<<SORRY_SERVER>>"] = "";
    } else {
        $tag["<<SORRY_SERVER>>"] = 'sorry_server '.$sorry;
    }

    $conftemp = change_template_tag($conftmpl, $tag);

    $tmpfname = tempnam($dirs, $vsfile);
    if ($tmpfname === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    $fh = fopen($tmpfname, "w");
    if ($fh === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    if (fwrite($fh, $conftemp) === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }
    fclose($fh);

    if (rename($tmpfname, $vsfile) === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }
    return TRUE;
}

/***********************************************************
 * change_tag_vsconf
 *
 * ��������ݻ��������Ƥη���
 *
 * [����]
 *        &$data              �ݻ�������
 * [�֤���]
 *        $tag                �ݻ����줿����
 **********************************************************/
function change_tag_vsconf (&$data)
{
    global $algo_list;
    global $kind_list;
    global $protocol_name;

    $tag["<<IPADDRESS>>"]              = escape_html($data["ipaddress"]);
    $tag["<<PORT>>"]                   = escape_html($data["port"]);
    $tag["<<DELAY_LOOP>>"]             = escape_html($data["delay_loop"]);
    $tag["<<PERSISTENCE_TIMEOUT>>"]    = escape_html($data["persistence_timeout"]);
    $tag["<<VIRTUALHOST>>"]            = escape_html($data["virtualhost"]);
    $tag["<<SORRY_SERVER_IPADDRESS>>"] = escape_html($data["sorry_server_ipaddress"]);
    $tag["<<SORRY_SERVER_PORT>>"]      = escape_html($data["sorry_server_port"]);

    /* selected���դ��� */
    $tag["<<LB_ALGO>>"] = "";
    foreach($algo_list as $key => $value) {
        if ($data["lb_algo"] == $key) {
            $tag["<<LB_ALGO>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<LB_ALGO>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    $tag["<<LB_KIND>>"] = "";
    foreach($kind_list as $key => $value) {
        if ($data["lb_kind"] == $key) {
            $tag["<<LB_KIND>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<LB_KIND>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    switch ($data["protocol"]) {
        case 0:
        case 1:
            $tag["<<PROTOCOL_NAME>>"] = $protocol_name[$data["protocol"]];
            break;
        default:
            $tag["<<PROTOCOL_NAME>>"] = "unknown";
            break;
    }

    $tag["<<PROTOCOL>>"] = $data["protocol"];

    return $tag;
}

/***********************************************************
 * �������
 **********************************************************/
/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* �С�����륵���Ф�¸�ߤ��뤫�γ�ǧ */
$result = check_vs_exists($_POST);
if ($result === 1) {
    err_location("index.php?e=1");
    exit(1);
} elseif ($result === 2) {
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    dgp_location("index.php", $err_msg);
    exit(0);
}

$filename     = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server.conf";
$tmplfilename = "../../../../tmpl/iluka/virtual_server.conf.tmpl";
$change_result = "";

/* �ܼ����ɽ������IP���ɥ쥹�ڤӥݡ����ֹ� */
$data["ipaddress"] = $_POST["ipaddress"];
$data["port"]      = $_POST["port"];
$data["protocol"]  = $_POST["protocol"];

$iport      = $data["ipaddress"]."_".$data["port"]."_".$data["protocol"];
$vsconfpass = $web_conf["iluka"]["keepalivedbasedir"].'virtual_server/'.$iport.'.conf';

/***********************************************************
 * main����
 **********************************************************/

if (isset($_POST["update"])) {
    if (check_form($_POST)) {

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $change_result = change_conffile($tmplfilename, $_POST, $iport);
        if ($ret === TRUE) {
            if (reload_status($filename, $iport) === FALSE) {
                result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            }
        }

        if(unlock_file($lock_fh) === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        if ($change_result === TRUE) {
            $msg = "�С�����륵���� " . $_POST["ipaddress"] . "  " .
                   $_POST["port"] . "/" . $protocol_name[$_POST["protocol"]] .
                   " ������򹹿����ޤ�����";
            dgp_location("index.php", $msg);
            exit(0);
        }
    }
    $tag = change_tag_vsconf($_POST);

/* ���ɽ������ */
} else {
    if (get_virtual_conf($vsconfpass, $data, $_POST) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        dgp_location("index.php", $err_msg);
        exit(0);
    }
    $tag = change_tag_vsconf($data); 
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* ���� ���� */
set_tag_common($tag);

/* �ڡ����ν��� */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
