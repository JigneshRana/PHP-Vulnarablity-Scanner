#!/usr/bin/python
# -*- coding: utf-8 -*-

# /!\ Detection Format (.*)function($vuln)(.*) matched by payload[0]+regex_indicators
regex_indicators = '\\((.*?)(\\$_GET\\[.*?\\]|\\$_FILES\\[.*?\\]|\\$_POST\\[.*?\\]|\\$_REQUEST\\[.*?\\]|\\$_COOKIES\\[.*?\\]|\\$_SESSION\\[.*?\\]|\\$(?!this|e-)[a-zA-Z0-9_]*)(.*?)\\)'

regex_indicators_simple ='\\(.*?\)'

#woring on https://pythex.org/
#exec(?<!curl_exec)\( (.*?)(\$_GET\[.*?\]|\$_FILES\[.*?\]|\$_POST\[.*?\]|\$_REQUEST\[.*?\]|\$_COOKIES\[.*?\]|\$_SESSION\[.*?\]|\$(?!this|e-)[a-zA-Z0-9_]*)(.*?)\)


# Function_Name:String, Vulnerability_Name:String, Protection_Function:Array
payloads = [

    # Remote Command Execution
    ["exec", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3","(?<!curl_exec)(?<!func_exec)(?<!jigexec)"],
    ["eval", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["popen", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["popen_ex", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["system", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["passthru", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["shell_exec", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["pcntl_exec", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["assert", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["proc_open", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["expect_popen", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["create_function", "Remote Command Execution", ["escapeshellarg", "escapeshellcmd"],"3",""],
    ["call_user_func", "Remote Code Execution", [],"3",""],
    ["call_user_func_array", "Remote Code Execution", [],"3",""],
  
    # MySQL(i) SQL Injection
    ["mysql_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_multi_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_send_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_master_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_master_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysql_unbuffered_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysql_db_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli::real_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_real_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli::query", "SQL Injection", ["mysql_real_escape_string"],"3",""],
    ["mysqli_query", "SQL Injection", ["mysql_real_escape_string"],"3",""],

    # PostgreSQL Injection
    ["pg_query", "SQL Injection", ["pg_escape_string", "pg_pconnect", "pg_connect"],"3",""],
    ["pg_send_query", "SQL Injection", ["pg_escape_string", "pg_pconnect", "pg_connect"],"3",""],

    # SQLite SQL Injection
    ["sqlite_array_query", "SQL Injection", ["sqlite_escape_string"],"3",""],
    ["sqlite_exec", "SQL Injection", ["sqlite_escape_string"],"3",""],
    ["sqlite_query", "SQL Injection", ["sqlite_escape_string"],"3",""],
    ["sqlite_single_query", "SQL Injection", ["sqlite_escape_string"],"3",""],
    ["sqlite_unbuffered_query", "SQL Injection", ["sqlite_escape_string"],"3",""],

    # PDO SQL Injection
    ["->arrayQuery", "SQL Injection", ["->prepare"],"3",""],
    ["->query", "SQL Injection", ["->prepare"],"3",""],
    ["->queryExec", "SQL Injection", ["->prepare"],"3",""],
    ["->singleQuery", "SQL Injection", ["->prepare"],"3",""],
    ["->querySingle", "SQL Injection", ["->prepare"],"3",""],
    ["->exec", "SQL Injection", ["->prepare"],"3",""],
    ["->execute", "SQL Injection", ["->prepare"],"3",""],
    ["->unbufferedQuery", "SQL Injection", ["->prepare"],"3",""],
    ["->real_query", "SQL Injection", ["->prepare"],"3",""],
    ["->multi_query", "SQL Injection", ["->prepare"],"3",""],
    ["->send_query", "SQL Injection", ["->prepare"],"3",""],  

    # Cubrid SQL Injection
    ["cubrid_unbuffered_query", "SQL Injection", ["cubrid_real_escape_string"],"3",""],
    ["cubrid_query", "SQL Injection", ["cubrid_real_escape_string"],"3",""],

    # MSSQL SQL Injection : Warning there is not any real_escape_string
    ["mssql_query", "SQL Injection", ["mssql_escape"],"3",""],

    # File Upload
    ["move_uploaded_file", "File Upload", [],"3",""],

    # File Inclusion / Path Traversal
    ["virtual", "File Inclusion", [],"1",""],
    ["include", "File Inclusion", [],"1",""],
    ["require", "File Inclusion", [],"1",""],
    ["include_once", "File Inclusion", [],"1",""],
    ["require_once", "File Inclusion", [],"1",""],

    ["readfile", "File Inclusion / Path Traversal", [],"2",""],
    ["file_get_contents", "File Inclusion / Path Traversal", [],"1",""],
    ["file_put_contents", "File Inclusion / Path Traversal", [],"2",""],
    ["show_source", "File Inclusion / Path Traversal", [],"2",""],
    ["fopen", "File Inclusion / Path Traversal", [],"2",""],
    ["file", "File Inclusion / Path Traversal", [],"2",""],
    ["fpassthru", "File Inclusion / Path Traversal", [],"3",""],
    ["gzopen", "File Inclusion / Path Traversal", [],"3",""],
    ["gzfile", "File Inclusion / Path Traversal", [],"3",""],
    ["gzpassthru", "File Inclusion / Path Traversal", [],"3",""],
    ["readgzfile", "File Inclusion / Path Traversal", [],"3",""],
    ["DirectoryIterator", "File Inclusion / Path Traversal", [],"3",""],
    ["stream_get_contents", "File Inclusion / Path Traversal", [],"2",""],
    ["copy", "File Inclusion / Path Traversal", [],"2","(?<!cc_copy)"],

    # PHP Objet Injection
    ["unserialize", "PHP Object Injection", [],"2",""],

    # Header Injection
    #[" header", "Header Injection", [],"1",""],
    ["HttpMessage::setHeaders", "Header Injection", [],"2",""],
    ["HttpRequest::setHeaders", "Header Injection", [],"2",""],

    # Weak Cryptographic Hash
    ["md5", "Weak Cryptographic Hash", [],"1",""],
    ["sha1", "Weak Cryptographic Hash", [],"1",""],

    # Information Leak
    ["phpinfo", "Information Leak", [],"3",""],
    ["debug_print_backtrace", "Information Leak", [],"3",""],
    ["show_source", "Information Leak", [],"3",""],
    ["highlight_file", "Information Leak", [],"3",""],

    # Others
    ["unlink", "Arbitrary File Deletion", [],"3",""],
    ["extract", "Arbitrary Variable Overwrite", [],"3",""],
    ["setcookie", "Arbitrary Cookie", [],"1",""],
    ["chmod", "Arbitrary File Permission", [],"3",""],
    ["mkdir", "Arbitrary Folder Creation", [],"3",""],
]
suggetion = [
    ["File Inclusion","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["Arbitrary File Deletion","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["Arbitrary Variable Overwrite","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["Arbitrary Cookie","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["Arbitrary File Permission","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["Arbitrary Folder Creation","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],

    ["Information Leak","Do Not Use Such Functions(phpinfo,debug_print_backtrace,show_source,highlight_file) Which Exposed Server Informations."],
    ["Weak Cryptographic Hash","md5 and sha1 Is Following Week Cryptographic, Which Could Be Decryptable"],
    ["Header Injection","Header Injection Vulnarability"],
    ["PHP Object Injection","PHP Object Injection Vulnarability"],
    ["File Inclusion / Path Traversal","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["File Inclusio","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["File Upload","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In Such Commands"],
    ["SQL Injection","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’] In SQL Query or Command"],
    ["Remote Command Execution","Apply Proper Validations.Do Not Directly Use $_GET/$_POST/$_REQUEST/$Cookie/$_SERVER[‘HTTP_HOST’]."],

]
