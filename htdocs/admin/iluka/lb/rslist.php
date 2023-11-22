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
 * �ꥢ�륵���а������� 
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

define("OPERATION", "Real_Server_List");
define("TMPLFILE", "iluka_lb_rslist.tmpl");

/*********************************************************
 * edit_rslist
 *
 * read,change,write,reload��ή��
 *
 * [����]
 * $file                real_server.conf�Υѥ�
 * $rsiport             �����ǡ���������IP_PORT
 * $act                 �Խ����������
 * &$data                �Խ�����ǡ���
 *
 * [�֤���]
 * TRUE                 ����
 * FALSE                �۾�  
 **********************************************************/
function edit_rslist ($file, $iport, $rsiport, $act, &$data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $vsconffile;

    if (read_rslist($file, $data) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    if (change_rslist($iport, $rsiport, $act, $data, $reload) === FALSE) {
        return TRUE;
    }

    if (write_rslist($file, $data) === FALSE){
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    if ($act === 'delete') {
        $msgs = delete_reals($iport, $rsiport);
        if ($msgs !== "") {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }
    }

    if ($reload === 1 && reload_status($vsconffile, $iport) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    }


    /* ͭ��->̵�������������� */
    if ($act === 'disable') {
        $err_msg = sprintf($msgarr['28076'][SCREEN_MSG], dirname2msg($rsiport));

    /* ̵��->ͭ�������������� */
    } elseif ($act === 'enable') {
        $err_msg = sprintf($msgarr['28077'][SCREEN_MSG], dirname2msg($rsiport));
    } else {
        /* ���Ƥκ�������������� */
        $err_msg = sprintf($msgarr['28078'][SCREEN_MSG], dirname2msg($rsiport));

        /* ����������ե����뤬�Ĥä��� */
        if ($msgs !== "") {
            $err_msg .= "<br>".$msgs;
        }
    }
    return TRUE;
}


/**********************************************************
 * change_rslist
 *
 * $data���ѹ�
 *
 * [����]
 *        $iport           �����ǡ����ΥС�����븡����IP_PORT
 *        $rsiport         �����ǡ����Υꥢ�븡����IP_PORT
 *        $act             ͭ��:̵���ѹ�������
 *        &$data           �Խ�����ǡ���
 *        &$reload         ����ɤ򤹤롦���ʤ�(0=���ʤ�,1=����)
 * [�֤���]
 *        TRUE             ����
 *        FALSE            ����
 **********************************************************/
function change_rslist($iport, $rsiport, $act, &$data, &$reload)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $reload = 1;

    $confpass = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/$iport/real_server.conf";
    $cht = count($data);

    for ($i = 0; $i < $cht; $i++) {
        $check = $data[$i]["rs_ipaddress"]."_".$data[$i]["rs_port"];
        if ($check == $rsiport) {
            /* ͭ����̵���ν��� */
            if ($act === 'disable') {
                /* �����ѹ�����Ƥ������ */
                if ($data[$i]["rsable"] === "disable") {
                    $err_msg = sprintf($msgarr['28079'][SCREEN_MSG], $confpass);
                    return FALSE;
                }
                $data[$i]["rsable"] = "disable";
                break;
            }

            /* ̵����ͭ���ν��� */
            if ($act === 'enable') {
                /* �����ѹ�����Ƥ������ */
                if ($data[$i]["rsable"] === "enable") {
                    $err_msg = sprintf($msgarr['28079'][SCREEN_MSG], $confpass);
                    return FALSE;
                }
                $data[$i]["rsable"] = "enable";
                break;
            }

            /* ������� */
            if ($act === 'delete') {
                if ($data[$i]["rsable"] === "disable") {
                    $reload = 0;
                }
                unset($data[$i]);
                $data = array_merge($data);
                break;
            }
        }
    }

    /* ����¸�ߤ��ʤ���� */
    if ($i === $cht) {
        $err_msg = sprintf($msgarr['28080'][SCREEN_MSG], $confpass);
        return FALSE;
    }

    return TRUE;
}

/**********************************************************
 * delete_reals
 *
 * .conf�κ��
 *
 * [����]
 *        $iport           �������С�����륵���Ф�[IP_PORT]
 *        $rsiport         �������ꥢ�륵���Ф�[IP_PORT]
 * [�֤���]
 *        $msgs            �������Ի��Υ��顼
 **********************************************************/
function delete_reals($iport, $rsiport)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $rsiport_file = $web_conf["iluka"]["keepalivedbasedir"].'virtual_server/'."$iport/".$rsiport.'.conf';

    $result = unlink($rsiport_file);
    if ($result === FALSE) {
        $err_msg = sprintf($msgarr['28082'][SCREEN_MSG], $rsiport_file);
        $log_msg = sprintf($msgarr['28082'][LOG_MSG], $rsiport_file);
        return $err_msg;
    }
    return "";
}

/**********************************************************
 * dirname2msg
 *
 * �ꥢ�륵��������ǥ��쥯�ȥ�̾��ɽ���ѥ�å��������Ѵ�����
 *
 * [����]
 *        $dirname         �ꥢ�륵��������ǥ��쥯�ȥ�̾
 * [�֤���]
 *        $msgs            ɽ���ѥ�å�����
 **********************************************************/
function dirname2msg($dirname)
{
    global $protocol_name;

    $parts = explode("_", $dirname, 2);
    if (count($parts) !== 2) {
        return $dirname;
    }

    return $parts[0] . " " . $parts[1];
}


/***********************************************************
 * �������
 **********************************************************/
$protocol_name = array("tcp", "udp");

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

$vsconffile = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server.conf";
$info_file = "../../../../tmpl/iluka_lb_rslist_info.tmpl";

$iport      = $_POST["ipaddress"]."_".$_POST["port"]."_".$_POST["protocol"];
$rsconffile = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/".$iport."/real_server.conf";

$tag["<<IPADDRESS>>"]   = $_POST["ipaddress"];
$tag["<<PORT>>"]        = $_POST["port"];
$tag["<<PROTOCOL>>"]    = $_POST["protocol"];
$tag["<<PROTOCOL_NAME>>"] = $protocol_name[$_POST["protocol"]];
$all = "";


/***********************************************************
 * main����
 **********************************************************/
if (isset($_POST["rs_act_class"])) {
    $msgadr = $_POST["rs_ipaddress"];
    $msgport = $_POST["rs_port"];
    $rsiport = $msgadr.'_'.$msgport;

    /* ͭ��->̵�� */
    if ($_POST["rs_act_class"] == "disable") {
        $act = 'disable';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_rslist ($rsconffile, $iport, $rsiport, $act, $data);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

    /* ̵��->ͭ�� */
    } elseif ($_POST["rs_act_class"] == "enable") {
        $act = 'enable';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_rslist ($rsconffile, $iport, $rsiport, $act, $data);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

    /* ��� */
    } elseif ($_POST["rs_act_class"] == "delete") {
        $act = 'delete';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_rslist ($rsconffile, $iport, $rsiport, $act, $data);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }
    }
}

/* ���ɽ�� */
$result = read_rslist($rsconffile, $data);
if ($result === FALSE) {
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    dgp_location("index.php", $err_msg);
    exit(0);
}
$result = read_template($info_file, $html);
if ($result === FALSE) {
    $err_msg = $msgarr['28056'][SCREEN_MSG];
    $log_msg = $msgarr['28056'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

if ($data !== "") {
    foreach($data as $key => $value) {
        $tag["<<RSABLE>>"] = $data[$key]["rsable"];
        if ($tag["<<RSABLE>>"] === 'enable') {
            $tag["<<RSACT>>"] = 'disable';
        }elseif ($tag["<<RSABLE>>"] === 'disable') {
            $tag["<<RSACT>>"] = 'enable';
        }
        $tag["<<RSIPADDRESS>>"] = $data[$key]["rs_ipaddress"];
        $tag["<<RSPORT>>"] = $data[$key]["rs_port"];
        $info_html = change_template_tag($html, $tag);
        $all .= $info_html;
    }
    $tag["<<REAL_SERVER_LIST>>"] = $all;
} else {
    $tag["<<REAL_SERVER_LIST>>"] = "";
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
