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
 * index.php
 * �С�����륵���а�������
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

define("OPERATION", "Virtual_Server_List");
define("TMPLFILE", "iluka_lb_vslist.tmpl");

/**********************************************************
 * change_vslist
 *
 * $data���ѹ�
 *
 * [����]
 *        $iport           �����ǡ���������IP_PORT
 *        $act             ͭ��:̵���ѹ�������
 *        &$data           �Խ�����ǡ���
 *        &$reload         �����˥���ɤ��롦���ʤ�(0=���ʤ�,1=����)
 * [�֤���]
 *        TRUE             ����
 *        FALSE            ����
 **********************************************************/
function change_vslist($iport, $act, &$data, &$reload)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $reload = 1;

    $filename = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server.conf";
    $cht = count($data);

    for ($i = 0; $i < $cht; $i++) {
        $check = $data[$i]["ipaddress"]."_".$data[$i]["port"].
                 "_".$data[$i]["protocol"];
        if ($check == $iport) {
            /* ͭ����̵���ν��� */
            if ($act === 'disable') {
                /* �����ѹ�����Ƥ������ */
                if ($data[$i]["able"] === "disable") {
                    $err_msg = sprintf($msgarr['28061'][SCREEN_MSG], $filename);
                    return FALSE;
                }
                $data[$i]["able"] = "disable";
                break;
            }

            /* ̵����ͭ���ν��� */
            if ($act === 'enable') {
                /* �����ѹ�����Ƥ������ */
                if ($data[$i]["able"] === "enable") {
                    $err_msg = sprintf($msgarr['28061'][SCREEN_MSG], $filename);
                    return FALSE;
                }
                $data[$i]["able"] = "enable";
                break;
            }

            /* ������� */
            if ($act === 'delete') {
                if ($data[$i]["able"] === "disable") {
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
        $err_msg = sprintf($msgarr['28068'][SCREEN_MSG], $filename);
        return FALSE;
    }

    return TRUE;
}


/**********************************************************
 * edit_vslist
 *
 * read,change,write,reload��ή��
 *
 * [����]
 *        $file            virtual_server.conf�Υѥ�
 *        $iport           �����ǡ���������IP_PORT
 *        $act             �Խ����������
 *        &$data           �Խ�����ǡ���
 * [�֤���]
 *        TRUE             ����
 *        FALSE            ����
 **********************************************************/
function edit_vslist ($file, $iport, $act, &$data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $result = read_vslist($file, $data);
    if ($result === FALSE) {
        return FALSE;
    }

    $result = change_vslist($iport, $act, $data, $reload);
    if ($result === FALSE) {
        return TRUE;
    }

    $result = write_vslist($file, $data);
    if ($result === FALSE){
        return FALSE;
    }

    if ($act === 'delete') {
        $msgs = delete_virtuals($iport);
        if ($msgs !== "") {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }
    }

    if ($reload === 1) {
        $result = reload_keepalived();
        if ($result === FALSE){
            return FALSE;
        }
    }

    /* ͭ��->̵�������������� */
    if ($act === 'disable') {
        $err_msg = sprintf($msgarr['28057'][SCREEN_MSG], dirname2msg($iport));

    /* ̵��->ͭ�������������� */
    } elseif ($act === 'enable') {
        $err_msg = sprintf($msgarr['28058'][SCREEN_MSG], dirname2msg($iport));
    } else { 

        /* ���Ƥκ�������������� */
        $err_msg = sprintf($msgarr['28062'][SCREEN_MSG], dirname2msg($iport));

        /* ����������ե����뤬�Ĥä��� */
        if ($msgs !== "") {
            $err_msg .= "<br>".$msgs;
        }
    }
    return TRUE;
}

/**********************************************************
 * delete_virtuals
 *
 * .conf�ڤӥǥ��쥯�ȥ�κ��
 *
 * [����]
 *        $iport           �������С�����륵���Ф�IP�ȥݡ���
 * [�֤���]
 *        $msgs            �������Ի��Υ��顼
 **********************************************************/
function delete_virtuals($iport)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $iport_file = $web_conf["iluka"]["keepalivedbasedir"].'virtual_server/'.$iport.'.conf';
    $iport_dir = $web_conf["iluka"]["keepalivedbasedir"].'virtual_server/'."$iport";
    $errdir = "";

    $result = unlink($iport_file);
    if ($result === FALSE) {
        $err_msg = sprintf($msgarr['28063'][SCREEN_MSG], $iport_file);
        $log_msg = sprintf($msgarr['28063'][LOG_MSG], $iport_file);
        return $msgs;
    }

    $dh = opendir($iport_dir);
    if ($dh === FALSE) {
        $err_msg = sprintf($msgarr['28065'][SCREEN_MSG], $iport_dir);
        $log_msg = sprintf($msgarr['28065'][LOG_MSG], $iport_dir);
        return $msgs;
    }
        
    while ($dirs = readdir($dh)) {
        if ((substr($dirs, -5)) === '.conf') {
            $dirs = "$iport_dir/"."$dirs";

            /* .conf�ե������� */
            $result = unlink($dirs);
            if ($result === FALSE) {
                $errdir .= " $dirs";
            }
            continue;

        } elseif ($dirs === '.') {
            continue;
        } elseif ($dirs === '..') {
            continue;
        }
        $errdir .= " $dirs";

    }
    closedir($dh);

    if ($errdir !== "") {
        $errdir = "$iport_dir$errdir";
        $msgs = sprintf($msgarr['28066'][SCREEN_MSG], $errdir);
        $log_msg = sprintf($msgarr['28066'][LOG_MSG], $errdir);
        return $msgs;
    }
    
    $result = rmdir($iport_dir);
    if ($result === FALSE) {
        $err_msg = sprintf($msgarr['28065'][SCREEN_MSG], $iport_dir);
        $log_msg = sprintf($msgarr['28065'][LOG_MSG], $iport_dir);
        return $msgs;
    }

    return "";
}

/**********************************************************
 * dirname2msg
 *
 * �С�����륵��������ǥ��쥯�ȥ�̾��ɽ���ѥ�å��������Ѵ�����
 *
 * [����]
 *        $dirname         �С�����륵��������ǥ��쥯�ȥ�̾
 * [�֤���]
 *        $msgs            ɽ���ѥ�å�����
 **********************************************************/
function dirname2msg($dirname)
{
    global $protocol_name;

    $parts = explode("_", $dirname, 3);
    if (count($parts) !== 3) {
        return $dirname;
    }

    switch ($parts[2]) {
        case 0:
        case 1:
            $proto = $protocol_name[$parts[2]];
            break;
        default:
            $proto = "unknown";
            break;
    }

    return $parts[0] . " " . $parts[1] . "/" . $proto;
}


/***********************************************************
 * ���������
 **********************************************************/
$all = "";

/***********************************************************
 * �������
 **********************************************************/

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* keepalived.conf��virtual_server.conf�ɤ߹���*/
$filename = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server.conf";
$info_filename = "../../../../tmpl/iluka_lb_vslist_info.tmpl";

/***********************************************************
 * main����
 **********************************************************/
$protocol_name = array("tcp", "udp");

if (isset($_POST["act_class"])) {
    $iport = $_POST["ipaddress"] . '_' .
             $_POST["port"] . '_' .
             $_POST["protocol"];

    /* ͭ�� -> ̵�� */
    if ($_POST["act_class"] == "disable") {
        $act = 'disable';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_vslist ($filename, $iport, $act, $data);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        }
 
        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }
          

    /* ̵�� -> ͭ�� */
    } elseif ($_POST["act_class"] == "enable") {
        $act = 'enable';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_vslist ($filename, $iport, $act, $data);
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
    } elseif ($_POST["act_class"] == 'delete') {
        $act = 'delete';

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = edit_vslist ($filename, $iport, $act, $data);
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

/* �������ɽ�� */
} else {
    $result = read_vslist($filename, $data);
    if ($result === FALSE) {
        $data = "";
        $tag["<<VIRTUAL_SERVER_LIST>>"] = "";
        $tag["<<IPADDRESS>>"] = "";
        $tag["<<PORT>>"] = "";
        $tag["<<PROTOCOL>>"] = "";
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    }
}


$result = read_template($info_filename, $html);
if ($result === FALSE) {
    $err_msg = $msgarr['28056'][SCREEN_MSG];
    $log_msg = $msgarr['28056'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
$all = "";
if ($data != "") {

    foreach($data as $key => $value) {
        $tag["<<ABLE>>"] = $data[$key]["able"];
        if ($tag["<<ABLE>>"] === 'enable') {
            $tag["<<ACT>>"] = 'disable';
        }elseif ($tag["<<ABLE>>"] === 'disable') {
            $tag["<<ACT>>"] = 'enable';
        }
        $tag["<<IPADDRESS>>"] = $data[$key]["ipaddress"];
        $tag["<<PORT>>"] = $data[$key]["port"];
        $tag["<<PROTOCOL>>"] = $data[$key]["protocol"];
        $tag["<<PROTOCOL_NAME>>"] = $protocol_name[$data[$key]["protocol"]];
        $info_html = change_template_tag($html, $tag);
        $all .= $info_html;
    }
}

$tag["<<VIRTUAL_SERVER_LIST>>"] = $all;

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
