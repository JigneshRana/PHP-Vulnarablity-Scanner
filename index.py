#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys
import argparse
import json
from detection import *

parser = argparse.ArgumentParser()
#parser.add_argument('--dir', action='store', dest='dir', help="Directory to analyse")
parser.add_argument('--file', action='store', dest='file', help="File/directory to analyse")
parser.add_argument('--list', action='store', dest='list', help="List of File to analyse")
parser.add_argument('--level', action='store', dest='level', help="Severity Level scanning (eg: 1,2,3)")
results = parser.parse_args()

severity_level = 1

if (results.level):
    severity_level = results.level
else:
    severity_level = 1

if (results.list == "True" ):
  multifile(results.file, 0,0,severity_level)

if os.path.isfile(results.file):
    analysis(results.file, 0,severity_level)
 
else:
    recursive(results.file, 0, 0,severity_level)