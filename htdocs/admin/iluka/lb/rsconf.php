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
 * リアルサーバ設定画面
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
グローバル変数
*********************************************************/
$health_check = array("HTTP_GET", "SSL_GET", "TCP_CHECK", "SMTP_CHECK", "MISC_CHECK", "UDP_CHECK", "DNS_CHECK");

/********************************************************
各ページ毎の設定
*********************************************************/

define("OPERATION", "Configuration virtual_server");
define("TMPLFILE", "iluka_lb_rsconf.tmpl");

/*********************************************************
 * conf_rsfile
 *
 * リアルサーバ設定ファイル編集
 *
 * [引数]
 *       $post               入力された値
 *       $vsiport            バーチャルサーバのIP_PORT
 *       $rsiport            リアルサーバのIP_PORT
 * [返り値]
 *       TRUE                 正常
 *       FALSE                異常  
 **********************************************************/
function conf_rsfile ($post,$vsiport, $rsiport)
{

    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $rsdir      = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/$vsiport/";
    $rsfile     = $rsdir.$rsiport.".conf";

    /* 設定ファイルが存在するかの確認 */
    if (!file_exists($rsfile)) {
        $err_msg = sprintf($msgarr['28086'][SCREEN_MSG], $rsfile);
        $log_msg = sprintf($msgarr['28086'][LOG_MSG], $rsfile);
        return FALSE;
    }

    if (replace_tmpls($post, $conftmpl) === FALSE) {
        return FALSE;
    }

    if (make_rsconf($rsdir, $rsfile, $conftmpl) === FALSE) {
        $err_msg = sprintf($msgarr['28086'][SCREEN_MSG], $rsfile);
        $log_msg = sprintf($msgarr['28086'][LOG_MSG], $rsfile);
        return FALSE;
    }

    return TRUE;
}

/***********************************************************
 * change_tag_rsconf
 *
 * 入力欄に保持する内容の決定
 *
 * [引数]
 *        $data              保持する値
 * [返り値]
 *        $tag                保持されたタグ
 **********************************************************/
function change_tag_rsconf ($data)
{
    global $health_check;
    global $protocol_name;

    $tag["<<IPADDRESS>>"]          = escape_html($data["ipaddress"]);
    $tag["<<PORT>>"]               = escape_html($data["port"]);
    $tag["<<PROTOCOL>>"]           = escape_html($data["protocol"]);
    $tag["<<PROTOCOL_NAME>>"]      = escape_html($protocol_name[$data["protocol"]]);
    $tag["<<RSIPADDRESS>>"]        = escape_html($data["rs_ipaddress"]);
    $tag["<<RSPORT>>"]             = escape_html($data["rs_port"]);
    $tag["<<WEIGHT>>"]             = escape_html($data["weight"]);
    $tag["<<PATH>>"]               = escape_html($data["path"]);
    $tag["<<DIGEST>>"]             = escape_html($data["digest"]);
    $tag["<<STATUS_CODE>>"]        = escape_html($data["status_code"]);
    $tag["<<NB_GET_RETRY>>"]       = escape_html($data["nb_get_retry"]);
    $tag["<<DELAY_BEFORE_RETRY>>"] = escape_html($data["delay_before_retry"]);
    $tag["<<CONNECT_IP>>"]         = escape_html($data["connect_ip"]);
    $tag["<<CONNECT_PORT>>"]       = escape_html($data["connect_port"]);
    $tag["<<BINDTO>>"]             = escape_html($data["bindto"]);
    $tag["<<BIND_PORT>>"]          = escape_html($data["bind_port"]);
    $tag["<<CONNECT_TIMEOUT>>"]    = escape_html($data["connect_timeout"]);
    $tag["<<HELO_NAME>>"]          = escape_html($data["helo_name"]);
    $tag["<<MISC_PATH>>"]          = escape_html($data["misc_path"]);
    $tag["<<MISC_TIMEOUT>>"]       = escape_html($data["misc_timeout"]);
    $tag["<<DNS_NAME>>"]           = escape_html($data["dns_name"]);
    $tag["<<SMTP_RETRY>>"]         = escape_html($data["smtp_retry"]);
    $tag["<<SMTP_DELAY_RETRY>>"]   = escape_html($data["smtp_delay_retry"]);

    $tag["<<H_CHECK>>"] = "";
    foreach($health_check as $key => $value) {
        if ($data["h_check"] == $key) {
            $tag["<<H_CHECK>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<H_CHECK>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    // UDP_CHECK: require_reply
    if (isset($data["udp_require_reply"]) && $data["udp_require_reply"] == 1) {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\">チェックしない</option>"
                                     . "<option value=\"1\" selected>チェックする</option>";
    } else {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\" selected>チェックしない</option>"
                                     . "<option value=\"1\">チェックする</option>";
    }

    // DNS_CHECK: dns_type
    $dns_type_list = array("SOA", "A", "NS", "CNAME", "MX", "TXT", "AAAA");
    $tag["<<DNS_TYPE>>"]  = "";
    foreach ($dns_type_list as $value) {
        if ($data["dns_type"] === $value) {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\" selected>" . $value . "</option>";
        } else {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\">" . $value . "</option>";
        }
    }

    return $tag;
}

/***********************************************************
 * rsconf_hold_tag
 *
 * エラー時の入力内容の保持
 *
 * [引数]
 *        $post               入力された値
 * [返り値]
 *        $tag                保持されたタグ
 **********************************************************/
function rsconf_hold_tag ($post)
{
    global $health_check;
    global $protocol_name;

    $tag["<<IPADDRESS>>"]          = escape_html($post["ipaddress"]);
    $tag["<<PORT>>"]               = escape_html($post["port"]);
    $tag["<<PROTOCOL>>"]           = escape_html($post["protocol"]);
    $tag["<<PROTOCOL_NAME>>"]      = escape_html($protocol_name[$post["protocol"]]);
    $tag["<<RSIPADDRESS>>"]        = escape_html($post["rs_ipaddress"]);
    $tag["<<RSPORT>>"]             = escape_html($post["rs_port"]);
    $tag["<<WEIGHT>>"]             = escape_html($post["weighting"]);
    $tag["<<HELO_NAME>>"]          = escape_html($post["helo_name"]);
    $tag["<<MISC_PATH>>"]          = escape_html($post["misc_path"]);
    $tag["<<MISC_TIMEOUT>>"]       = escape_html($post["misc_timeout"]);
    $tag["<<PATH>>"]               = escape_html($post["http_path"]);
    $tag["<<DIGEST>>"]             = escape_html($post["http_digest"]);
    $tag["<<STATUS_CODE>>"]        = escape_html($post["http_status_code"]);
    $tag["<<NB_GET_RETRY>>"]       = escape_html($post["nb_get_retry"]);
    $tag["<<DELAY_BEFORE_RETRY>>"] = escape_html($post["http_delay_retry"]);
    $tag["<<SMTP_RETRY>>"]         = escape_html($post["smtp_retry"]);
    $tag["<<SMTP_DELAY_RETRY>>"]   = escape_html($post["smtp_delay_retry"]);
    $tag["<<DNS_NAME>>"]           = escape_html($post["dns_name"]);

    if (isset($post["udp_connect_require_reply"]) && $post["udp_connect_require_reply"] == 1) {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\">チェックしない</option>"
                                       . "<option value=\"1\" selected>チェックする</option>";
    } else {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\" selected>チェックしない</option>"
                                       . "<option value=\"1\">チェックする</option>";
    }

    $dns_type_list = array("SOA", "A", "NS", "CNAME", "MX", "TXT", "AAAA");
    $tag["<<DNS_TYPE>>"]  = "";
    foreach ($dns_type_list as $value) {
        if ($post["dns_type"] === $value) {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\" selected>" . $value . "</option>";
        } else {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\">" . $value . "</option>";
        }
    }

    if ($post["h_check"] == 0 || $post["h_check"] == 1 || $post["h_check"] == 4) {
        $connect_ipaddress         = $post["http_connect_ipaddress"];
        $connect_port              = $post["http_connect_port"];
        $connect_source_ipaddress  = $post["http_connect_source_ipaddress"];
        $connect_source_port       = $post["http_connect_source_port"];
        $connect_timeout           = $post["http_connect_timeout"];
    } elseif ($post["h_check"] == 2) {
        $connect_ipaddress         = $post["tcp_connect_ipaddress"];
        $connect_port              = $post["tcp_connect_port"];
        $connect_source_ipaddress  = $post["tcp_connect_source_ipaddress"];
        $connect_source_port       = $post["tcp_connect_source_port"];
        $connect_timeout           = $post["tcp_connect_timeout"];
    } elseif ($post["h_check"] == 3) {
        $connect_ipaddress         = $post["smtp_connect_ipaddress"];
        $connect_port              = $post["smtp_connect_port"];
        $connect_source_ipaddress  = $post["smtp_connect_source_ipaddress"];
        $connect_source_port       = $post["smtp_connect_source_port"];
        $connect_timeout           = $post["smtp_connect_timeout"];
    } elseif ($post["h_check"] == 5) {
        $connect_ipaddress         = $post["udp_connect_ipaddress"];
        $connect_port              = $post["udp_connect_port"];
        $connect_source_ipaddress  = $post["udp_connect_source_ipaddress"];
        $connect_source_port       = $post["udp_connect_source_port"];
        $connect_timeout           = $post["udp_connect_timeout"];
    } elseif ($post["h_check"] == 6) {
        $connect_ipaddress         = $post["dns_connect_ipaddress"];
        $connect_port              = $post["dns_connect_port"];
        $connect_source_ipaddress  = $post["dns_connect_source_ipaddress"];
        $connect_source_port       = $post["dns_connect_source_port"];
        $connect_timeout           = $post["dns_connect_timeout"];
    }

    $tag["<<CONNECT_IP>>"]         = escape_html($connect_ipaddress);
    $tag["<<CONNECT_PORT>>"]       = escape_html($connect_port);
    $tag["<<BINDTO>>"]             = escape_html($connect_source_ipaddress);
    $tag["<<BIND_PORT>>"]          = escape_html($connect_source_port);
    $tag["<<CONNECT_TIMEOUT>>"]    = escape_html($connect_timeout);

    /* selectedを付ける */
    $tag["<<H_CHECK>>"] = "";
    foreach($health_check as $key => $value) {
        if ($post["h_check"] == $key) {
            $tag["<<H_CHECK>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<H_CHECK>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    return $tag;
}

/***********************************************************
 * 初期処理
 **********************************************************/
$protocol_name = array("tcp", "udp");

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* バーチャルサーバが存在するかの確認 */
$result = check_vs_exists($_POST);
if ($result === 1) {
    err_location("index.php?e=1");
    exit(1);
} elseif ($result === 2) {
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    dgp_location("index.php", $err_msg);
    exit(0);
}


/* リアルサーバが存在するかの確認 */
$result = check_rs_exists($_POST);
if ($result === 1) {
    err_location("index.php?e=1");
    exit(1);
} elseif ($result === 2) {
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    dgp_location("index.php", $err_msg);
    exit(0);
}

/* tmpl一覧 */
$tmpl_list = array("../../../../tmpl/iluka/http_get.tmpl",
                   "../../../../tmpl/iluka/ssl_get.tmpl",
                   "../../../../tmpl/iluka/tcp_check.tmpl",
                   "../../../../tmpl/iluka/smtp_check.tmpl",
                   "../../../../tmpl/iluka/misc_check.tmpl",
                   "../../../../tmpl/iluka/udp_check.tmpl",
                   "../../../../tmpl/iluka/dns_check.tmpl");

$reload_result = "";
/***********************************************************
 * main処理
 **********************************************************/
if (isset($_POST["update"])) {
    if (rsconf_check_form($_POST)) {

        $vsiport    = $_POST["ipaddress"]."_".$_POST["port"]."_".$_POST["protocol"];
        $rsiport    = $_POST["rs_ipaddress"]."_".$_POST["rs_port"];

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $result = conf_rsfile($_POST, $vsiport, $rsiport);
        if ($result === TRUE) {
            $reload_result = rsconf_reload_status($_POST, $vsiport, $rsiport);
            if ($reload_result === FALSE) {
                result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            }
        }

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        if ($reload_result === TRUE) {
            $sesskey = $_POST["sk"];
            $msg = "リアルサーバ ".$_POST["rs_ipaddress"]." ".$_POST["rs_port"]." の設定を更新しました。";
            $post = array("ipaddress" => $_POST["ipaddress"],
                          "port"      => $_POST["port"],
                          "protocol"  => $_POST["protocol"]);
            iluka_location("rslist.php", $msg, $post);
            exit(0);
        }
    }
    $tag = rsconf_hold_tag($_POST);

} elseif (isset($_POST["cancel"])) {
    $sesskey = $_POST["sk"];
    $msg = ""; 
    $post = array("ipaddress"   => $_POST["ipaddress"],
                  "port"        => $_POST["port"],
                  "protocol"    => $_POST["protocol"],
                  "rs_ipaddress" => $_POST["rs_ipaddress"],
                  "rs_port"      => $_POST["rs_port"]);
    iluka_location("rslist.php",$msg, $post);
    exit(0);

/* 初期表示 */
} else {
    if (get_rsconf($_POST, $data) === FALSE) {
        result_log($log_msg, LOG_ERR);
        dgp_location("../", $err_msg);
        exit(0);
    }
    $tag = change_tag_rsconf($data);
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
