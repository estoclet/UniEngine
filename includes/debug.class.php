<?php

if(!defined('INSIDE')){ die('Access Denied!');}

class DBErrorHandler {
    var $isHandlingError = false;
    var $lastErrorMessage = "";

    function error($message) {
        global $_DBLink, $_User, $_EnginePath;

        if ($this->isHandlingError) {
            throw new RuntimeException(
                "DBErrorHandler: Nesting Prevention!\n" .
                $this->lastErrorMessage
            );
        }

        $this->isHandlingError = true;

        define('IN_ERROR', true);

        if (LOCALHOST) {
            require($_EnginePath . 'config.localhost.php');
        } else if (TESTSERVER) {
            require($_EnginePath . 'config.testserver.php');
        } else {
            require($_EnginePath . 'config.php');
        }

        if (!$_DBLink) {
            throw new RuntimeException("DBErrorHandler: DBDriver Connection Error #01");
        }

        if (empty($_User['id'])) {
            $_User['id'] = '0';
        }

        $Replace_Search = [
            '{{table}}',
            '{{prefix}}'
        ];
        $Replace_Replace = [
            $__ServerConnectionSettings['prefix'] . 'errors',
            $__ServerConnectionSettings['prefix']
        ];

        $EscapedMessage = $_DBLink->escape_string($message);

        $SQLQuery_InsertError = (
            "INSERT INTO {{table}} SET " .
            "`error_sender` = {$_User['id']}, " .
            "`error_time` = UNIX_TIMESTAMP(), " .
            "`error_text` = '{$EscapedMessage}' " .
            ";"
        );
        $SQLQuery_InsertError = str_replace(
            $Replace_Search,
            $Replace_Replace,
            $SQLQuery_InsertError
        );

        $_DBLink->query($SQLQuery_InsertError);

        if ($_DBLink->errno) {
            throw new RuntimeException(
                "DBDriver Fatal Error #01\n" .
                $_DBLink->error
            );
        }

        $ErrorID = $_DBLink->insert_id;

        $ErrorMsg = 'An Error occured!<br/>Error ID: <b>'. $ErrorID .'</b>';
        $this->lastErrorMessage = $ErrorMsg;

        if (!function_exists('message')) {
            echo $ErrorMsg;
        } else {
            message($ErrorMsg, 'System Error!');
        }

        $_DBLink->close();

        die();
    }
}

?>
