#!/usr/bin/python
# -*- coding: utf-8 -*-
import os
import re
import datetime
import types
from whitelist import *
from payloads import suggetion


# Format the source code in order to improve the detection
def clean_source_and_format(content):
    # Clean up - replace tab by space
    content = content.replace("    ", " ")

    # Quickfix to detect both echo("something") and echo "something"
    content = content.replace("echo ", "echo(")
    content = content.replace(";", ");")
    return content

# Replace the nth occurrence of a string
# Inspired from https://stackoverflow.com/questions/35091557/replace-nth-occurrence-of-substring-in-string
def nth_replace(string, old, new, n):
    if string.count(old) >= n:
        left_join = old
        right_join = old
        groups = string.split(old)
        nth_split = [left_join.join(groups[:n]), right_join.join(groups[n:])]
        return new.join(nth_split)
    return string.replace(old, new)
    
# Display the found vulnerability with basic information like the line
def display(path, payload, vulnerability, line, declaration_text, declaration_line, colored, occurrence, plain,simple_matches):
    
    
    file_path = path.split(e_seperator)

    if(len(file_path) >= 2):
       
        custom_file_path = e_projectname+file_path[1]
        logstr(custom_file_path)
        #logstr(e_projectname)
        #logstr(e_seperator)

        import hashlib
        hash_object = hashlib.sha256("".join(simple_matches).encode()) 
        code_hex_dig = hash_object.hexdigest()

        path_hash_object = hashlib.sha256("".join(custom_file_path).encode())
        path_hex_dig = path_hash_object.hexdigest()

        for i in whitelist:
            if(i[0] == path_hex_dig):
                for dig in i[1]:
                    if(code_hex_dig == dig):
                        return False;
        
            #logstr(custom_file_path[-7:])
            if(custom_file_path[-7:] == "dao.php"):
                dao_path = custom_file_path[-7:]
                if(i[0] == dao_path):
                    for dig in i[1]:
                        if(code_hex_dig == dig):
                            return False;

            if(i[0] == "commonwhitelist"):
                    for dig in i[1]:
                        if(code_hex_dig == dig):
                            return False;


    '''
    Red = '\033[91m'
    Green = '\033[92m'
    Blue = '\033[94m'
    Cyan = '\033[96m'
    White = '\033[97m'
    Yellow = '\033[93m'
    Magenta = '\033[95m'
    Grey = '\033[90m'
    Black = '\033[90m'
    Default = '\033[99m'
    '''
    if(payload[3]):
        str_severity = payload[3]
        str_color = '\033[93m'
        
        if(payload[3] == "1"):
            str_severity = payload[3]
            str_color = '\033[93m'
        elif(payload[3] == "2"):
            str_severity = payload[3]
            str_color = '\033[94m'
        elif(payload[3] == "3"):
            str_severity = payload[3]
            str_color = '\033[91m'

            
    
    # Potential vulnerability found :  SQL Injection
    header = "{}Potential vulnerability found : {}{}{}{}{}".format('' if plain else '\033[1m', '' if plain else '\033[92m', payload[1],str_color, ' Severity L'+str_severity, '' if plain else '\033[0m')

    
    # Line  25  in test/sqli.php
    line = "n°{}{}{} in {}".format('' if plain else '\033[92m', line, '' if plain else '\033[0m', path)

    # Code : include($_GET['patisserie'])
    vuln = nth_replace("".join(vulnerability), colored, "{}".format('' if plain else '\033[92m') + colored + "{}".format('' if plain else '\033[0m'), occurrence)
    #vuln = "{}({})".format(payload[0], vuln)
    vuln = "{}".format(vuln)

    
    # Final Display
    rows, columns = os.popen('stty size', 'r').read().split()
    print("-" * (int(columns) - 1))
    print("Name        \t{}".format(header))
    print("-" * (int(columns) - 1))
    print("{}Line {}             {}".format('' if plain else '\033[1m', '' if plain else '\033[0m', line))
    print("{}Code {}             {}".format('' if plain else '\033[1m', '' if plain else '\033[0m', vuln))
    
    # Declared at line 1 : $dest = $_GET['who'];
    if "$_" not in colored:
        declared = "Undeclared in the file"
        if declaration_text != "":
            declared = "Line n°{}{}{} : {}".format('' if plain else '\033[0;92m', declaration_line, '' if plain else '\033[0m', declaration_text)

        print("{}Declaration {}      {}".format('' if plain else '\033[1m', '' if plain else '\033[0m', declared))

    print("{}NonHashCode {}      {}".format('' if plain else '\033[1m', '' if plain else '\033[0m', "".join(simple_matches)))
    
    
    sg=get_suggetion(payload[1])
    if(sg):
        print("{}Recommendation {}   {}".format('' if plain else '\033[1m', '' if plain else '\033[0m', sg))

    # Small delimiter
    print("")

# Check the line to detect an eventual protection
def check_protection(payload, match):
    for protection in payload:
        if protection in "".join(match):
            return True
    return False

# Check the line to detect an eventual protection
def get_suggetion(payload):
    for slist in suggetion:
        if(slist[0] == payload):
            return slist[1]
    return False

# Check exception - When it's a function($SOMETHING) Match declaration $SOMETHING = ...
def check_exception(match):
    exceptions = ["_GET", "_REQUEST", "_POST", "_COOKIES", "_FILES"]
    for exception in exceptions:
        if exception in match:
            return True
    return False

def logstr(string):
    today = datetime.datetime.now() 
    logfile_name = "scannin_log" + today.strftime("%Y%m%d") +".log"
    f = open("logs/"+logfile_name, "a")
    
    if isinstance(string, list):
        str1 = ','.join(str(e) for e in string)
        string = str1

    log_string=os.environ.get('USER')+" ["+today.strftime('%Y-%m-%d %H:%M:%S')+"] "+str(string)
    f.write(log_string + "\n")
    f.close()
    return False

# Check declaration
def check_declaration(content, vuln, path):
    # Follow and parse include, then add it's content
    regex_declaration = re.compile("(include.*?|require.*?)\\([\"\'](.*?)[\"\']\\)")
    includes = regex_declaration.findall(content)

    # Path is the path of the current scanned file, we can use it to compute the relative include
    for include in includes:
        relative_include = os.path.dirname(path) + "/"
        try:
            path_include = relative_include + include[1]
            with open(path_include, 'r') as f:
                content = f.read() + content
        except Exception as e:
            return False, "", ""

    # Extract declaration - for ($something as $somethingelse)
    vulnerability = vuln[1:].replace(')', '\\)').replace('(', '\\(')
    regex_declaration2 = re.compile("\\$(.*?)([\t ]*)as(?!=)([\t ]*)\\$" + vulnerability)
    declaration2 = regex_declaration2.findall(content)
    if len(declaration2) > 0:
        return check_declaration(content, "$" + declaration2[0][0], path)

    # Extract declaration - $something = $_GET['something']
    regex_declaration = re.compile("\\$" + vulnerability + "([\t ]*)=(?!=)(.*)")
    declaration = regex_declaration.findall(content)
    if len(declaration) > 0:

        # Check constant then return True if constant because it's false positive
        declaration_text = "$" + vulnerability + declaration[0][0] + "=" + declaration[0][1]
        line_declaration = find_line_declaration(declaration_text, content)
        regex_constant = re.compile("\\$" + vuln[1:] + "([\t ]*)=[\t ]*?([\"\'(]*?[a-zA-Z0-9{}_\\(\\)@\\.,!: ]*?[\"\')]*?);")
        false_positive = regex_constant.match(declaration_text)

        if false_positive:
            return True, "", ""
        return False, declaration_text, line_declaration

    return False, "", ""    


# Find the line where the vulnerability is located
def find_line_vuln(payload, vulnerability, content):
    content = content.split('\n')
    #logstr(str(len(content)))
    for i in range(len(content)):
        #logstr(str(i))
        if payload[0] + '(' + vulnerability[0] + vulnerability[1] + vulnerability[2] + ')' in content[i]:
            #logstr(payload[0] + '(' + vulnerability[0] + vulnerability[1] + vulnerability[2] + ')'+"--"+str(i))
            #return str(i - 1) #jig
            return str(i + 1)
    return "-1"

# Find the line where the entry point is declared
# TODO: should be an array of the declaration and modifications
def find_line_declaration(declaration, content):
    content = content.split('\n')
    for i in range(len(content)):
        if declaration in content[i]:
            return str(i)
    return "-1"    