#!/usr/bin/python
# -*- coding: utf-8 -*-

#sha256 converter 
#https://passwordsgenerator.net/sha256-hash-generator/
whitelist = [
    ["Review/RES-2732/sample1.php",
        ["bfaeda587063c14e8fc289313f51d617ee61868fb3ab986d4b9f1be0a1f02c41","abc"],
        ["exec($_GET['a'])","abc"]
    ],
    ["saasfinal/booking/database/processdao.php",
        ["508549a5bb6eb8e07ad83417ab40b6482ebca70becb71580535c08a3fe8bb6d4","7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ["file_get_contents($file)",'eval("$str)eval("\$str = \"$str\")']
    ],
    ["saasfinal/booking/database/multiproperty_masterdao.php",
        ["ad2ede7b224011fda52ef67af1424731e85dee0a77c3530a2eac8e9874f083f4"],
        ['exec("php ".dirname(__FILE__)exec("SET NAMES utf8")']
    ],
    ["dao.php",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ['eval("$str)eval("\$str = \"$str\")']
    ],
    ["saasfinal/booking/database/audittrail.php",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ['eval("$str)eval("\$str = \"$str\")']
    ]

    

    
]