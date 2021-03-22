# PHP-Vulnerability-Scanner
Find pattern base vulnerable codes and provide suggestions

# How It works
python3 index.py --file [project file or folder] [Options]

# Scan Simple File 
**PHP-Vulnerability-Scanner$** python3 index.py --file /home/projectfolder/index.php

# Scan For Multiple Files
When you want to scan for multiple files, it requires passing the "--list True" Option.
**PHP-Vulnerability-Scanner$** python3 index.py --file /home/projectfolder/index.php,/home/location1/module/connection.php --list True

# Other Options
python3 index.py --help

**optional arguments:**
  -h, --help     show this help message and exit
  --file FILE    File/directory to analyse
  --list LIST    List of File to analyse
  --level LEVEL  Severity Level scanning (eg: 1,2,3)


# Advantages
- Easy To customize code and logic100+ PHP Functions and patterns
- Hash256 base whitelist false-positive result so can be used in your daily code deployment process
- Junior Developer also can able to understand the severity with helpful suggestions
- Level vise scanning 
