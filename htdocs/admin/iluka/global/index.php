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
 * �����Х��������
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 *
 **********************************************************/
include("../initial");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibiluka");

/********************************************************
*�ƥڡ����������
*********************************************************/

define("OPERATION", "Global_Setting");
define("TMPLFILE", "iluka_global.tmpl");


/********************************************************
 * check_form_global
 *
 * �����ͤη��������å�
 *
 * [����]
 * $notification_email       ������᡼�륢�ɥ쥹��������
 * $notification_email_from  �������᡼�륢�ɥ쥹��������
 * $smtp_server              SMTP�����Ф�������
 * $smtp_connect_timeout     SMTP��������³�����ॢ���Ȥ�������
 * [�֤���]
 *        TRUE             ����
 *        FALSE            ���顼
 *
 **********************************************************/
function check_form_global($notification_email, $notification_email_from, $smtp_server, $smtp_connect_timeout)
{

    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    /* notification_email */
    foreach($notification_email as $value) {
        if (check_mail($value) === FALSE) {
            $err_msg = $msgarr['28046'][SCREEN_MSG];
            return FALSE;
        }
    }
    
    /* notification_email_from */
    if (check_mail($notification_email_from) === FALSE) {
        $err_msg = $msgarr['28047'][SCREEN_MSG];
        return FALSE;
    }

    /* smtp_server */
    if (strlen($smtp_server) === 0) {
        $err_msg = $msgarr['28048'][SCREEN_MSG];
        return FALSE;
    }
    if (strlen($smtp_server) > 256) {
        $err_msg = $msgarr['28048'][SCREEN_MSG];
        return FALSE;
    }
    $num = "0123456789";
    $sl = "abcdefghijklmnopqrstuvwxyz";
    $ll = strtoupper($sl);
    $sym = "$%&'*+-/=?^_~.";
    $allow_letter = $num . $sl . $ll . $sym;
    if (strspn($smtp_server, $allow_letter) != strlen($smtp_server)) {
        $err_msg = $msgarr['28048'][SCREEN_MSG];
        return FALSE;
    }

    /* smtp_connect_timeout */
    if (strlen($smtp_connect_timeout) === 0) {
        $err_msg = $msgarr['28049'][SCREEN_MSG];
        return FALSE;
    }
    if (strlen($smtp_connect_timeout) > 5) {
        $err_msg = $msgarr['28049'][SCREEN_MSG];
        return FALSE;
    }
    $ret = ctype_digit($smtp_connect_timeout);
    if ($ret === FALSE) {
        $err_msg = $msgarr['28049'][SCREEN_MSG];
        return FALSE;
    }

    return TRUE;
}

/********************************************************
 *write_process
 *
 * keepalived.conf��񤭹������
 *
 * [����]
 *        $mail      ������᡼�륢�ɥ쥹��������
 *        $from      �������᡼�륢�ɥ쥹��������
 *        $server    SMTP�����Ф�������
 *        $tout      SMTP��������³�����ॢ���Ȥ�������
 * [�֤���]
 *        0          ����
 *        1          ���顼
 *        2          �����ƥ२�顼
 **********************************************************/
function write_process ($filename, $data, $tmplfilename)
{
 
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    /* write_global�ƤӽФ� */
    $result = write_global($filename, $data, $tmplfilename);
    if ($result === 1) {
        $err_msg = sprintf($msgarr['28050'][SCREEN_MSG], $filename);
        $log_msg = sprintf($msgarr['28050'][LOG_MSG], $filename);
        return 1;
    }
    if ($result === 2) {
        $err_msg = $msgarr['28054'][SCREEN_MSG];
        $log_msg = $msgarr['28054'][LOG_MSG];
        return 2;
    }


    /* ����ɽ��� */
    system($web_conf["iluka"]["keepalivedreloadscript"], $result);
    if ($result !== 0) {
        $err_msg = $msgarr['28051'][SCREEN_MSG];
        $log_msg = $msgarr['28051'][LOG_MSG];
        return 1;
    }

    return 0;
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

/* keepalived.conf�ɤ߹��� */
$tmplfilename = "../../../../tmpl/iluka/keepalived.conf.tmpl";
$filename = $web_conf["iluka"]["keepalivedbasedir"]."keepalived.conf";
/***********************************************************
 * main����
 **********************************************************/

/* keepalived.conf���� */
if (isset($_POST["update"])) {

    $check  = explode(",", $_POST["notification_email"]);
    $result = check_form_global($check,
                                $_POST["notification_email_from"],
                                $_POST["smtp_server"],
                                $_POST["smtp_connect_timeout"]);

    if ($result === TRUE) {

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        /* $data������� */
        $data["notification_email"] = $check;
        $data["notification_email_from"] = $_POST["notification_email_from"];
        $data["smtp_server"] = $_POST["smtp_server"];
        $data["smtp_connect_timeout"] = $_POST["smtp_connect_timeout"];

        $result = write_process ($filename, $data, $tmplfilename);
        if ($result === 2) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(1);
        }
        if ($result === 1) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        } else {
            $err_msg = $msgarr['28052'][SCREEN_MSG];
        }

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }
    } 

    /* post���줿������Ƥ��ݻ� */
    $tag["<<NOTIFICATION_EMAIL>>"]      = escape_html($_POST["notification_email"]);
    $tag["<<NOTIFICATION_EMAIL_FROM>>"] = escape_html($_POST["notification_email_from"]);
    $tag["<<SMTP_SERVER>>"]             = escape_html($_POST["smtp_server"]);
    $tag["<<SMTP_CONNECT_TIMEOUT>>"]    = escape_html($_POST["smtp_connect_timeout"]);
   
/* ������̽��� */
} else {

    /* keepalived.conf�ɤ߹��� */
    $result = read_global($filename, $data);
    if($result === FALSE) {
        $tag["<<NOTIFICATION_EMAIL>>"]      = "";
        $tag["<<NOTIFICATION_EMAIL_FROM>>"] = "";
        $tag["<<SMTP_SERVER>>"]             = "";
        $tag["<<SMTP_CONNECT_TIMEOUT>>"]    = "";
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);

    } else {
        $notification_email = implode(",", $data["notification_email"]);
        $tag["<<NOTIFICATION_EMAIL>>"]      = escape_html($notification_email);
        $tag["<<NOTIFICATION_EMAIL_FROM>>"] = escape_html($data["notification_email_from"]);
        $tag["<<SMTP_SERVER>>"]             = escape_html($data["smtp_server"]);
        $tag["<<SMTP_CONNECT_TIMEOUT>>"]    = escape_html($data["smtp_connect_timeout"]);
    }
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
