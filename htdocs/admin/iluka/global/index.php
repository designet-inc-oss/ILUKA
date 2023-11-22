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
 * グローバル設定画面
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
*各ページ毎の設定
*********************************************************/

define("OPERATION", "Global_Setting");
define("TMPLFILE", "iluka_global.tmpl");


/********************************************************
 * check_form_global
 *
 * 入力値の形式チェック
 *
 * [引数]
 * $notification_email       通知先メールアドレスの入力値
 * $notification_email_from  送信元メールアドレスの入力値
 * $smtp_server              SMTPサーバの入力値
 * $smtp_connect_timeout     SMTPサーバ接続タイムアウトの入力値
 * [返り値]
 *        TRUE             正常
 *        FALSE            エラー
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
 * keepalived.confを書き込む処理
 *
 * [引数]
 *        $mail      通知先メールアドレスの入力値
 *        $from      送信元メールアドレスの入力値
 *        $server    SMTPサーバの入力値
 *        $tout      SMTPサーバ接続タイムアウトの入力値
 * [返り値]
 *        0          正常
 *        1          エラー
 *        2          システムエラー
 **********************************************************/
function write_process ($filename, $data, $tmplfilename)
{
 
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    /* write_global呼び出し */
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


    /* リロード処理 */
    system($web_conf["iluka"]["keepalivedreloadscript"], $result);
    if ($result !== 0) {
        $err_msg = $msgarr['28051'][SCREEN_MSG];
        $log_msg = $msgarr['28051'][LOG_MSG];
        return 1;
    }

    return 0;
} 

/***********************************************************
 * 初期処理
 **********************************************************/

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* keepalived.conf読み込み */
$tmplfilename = "../../../../tmpl/iluka/keepalived.conf.tmpl";
$filename = $web_conf["iluka"]["keepalivedbasedir"]."keepalived.conf";
/***********************************************************
 * main処理
 **********************************************************/

/* keepalived.conf更新 */
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

        /* $dataに入れる */
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

    /* postされた後の内容の保持 */
    $tag["<<NOTIFICATION_EMAIL>>"]      = escape_html($_POST["notification_email"]);
    $tag["<<NOTIFICATION_EMAIL_FROM>>"] = escape_html($_POST["notification_email_from"]);
    $tag["<<SMTP_SERVER>>"]             = escape_html($_POST["smtp_server"]);
    $tag["<<SMTP_CONNECT_TIMEOUT>>"]    = escape_html($_POST["smtp_connect_timeout"]);
   
/* 初期画面処理 */
} else {

    /* keepalived.conf読み込み */
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
 * 表示処理
 **********************************************************/

/* タグ 設定 */
set_tag_common($tag);

/* ページの出力 */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
