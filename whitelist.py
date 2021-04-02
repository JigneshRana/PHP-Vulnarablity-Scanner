#!/usr/bin/python
# -*- coding: utf-8 -*-

#sha256 converter 
#https://passwordsgenerator.net/sha256-hash-generator/
e_projectname='saasfinal'
e_seperator='saasfinal'
#1st key is hash of filepath ( hash is done for security reaosn)
#2nd is has array of pattern 
#3d is sample list code for hash. it has no dependancy.
whitelist = [
    ["43981d18b0ff88e688c15528b647e647293b68a3f8245caea004870c81817ce9",
        ["bfaeda587063c14e8fc289313f51d617ee61868fb3ab986d4b9f1be0a1f02c41","abc"],
        ["exec($_GET['a'])","abc"]
    ],
    ["6e2d017d3043474a068edc936c2a8105dde0514e4fdb2be0e6317b91abcb4866",
        ["508549a5bb6eb8e07ad83417ab40b6482ebca70becb71580535c08a3fe8bb6d4","7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ["file_get_contents($file)",'eval("$str)eval("\$str = \"$str\")']
    ],
    ["d3de3c200bb5198709ca8045b981e0839c68011a01a207d92c79c5c0f0fe8ef0",
        ["ad2ede7b224011fda52ef67af1424731e85dee0a77c3530a2eac8e9874f083f4"],
        ['exec("php ".dirname(__FILE__)exec("SET NAMES utf8")']
    ],
    ["dao.php",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1","9667f45bfb4c5ae4785b3dfba3dc1096cf954008a9841f08db1f2643141ddc7a"],
        ['eval("$str)eval("\$str = \"$str\")','eval()eval("$str)eval("\$str = \"$str\")']
    ],
    ["e8d9c25c5d10795ddad571a1aa6bf87293dd52ea482f2183f30b00583127d5d6",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ['eval("$str)eval("\$str = \"$str\")']
    ],
    ["e8d9c25c5d10795ddad571a1aa6bf87293dd52ea482f2183f30b00583127d5d6",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1"],
        ['eval("$str)eval("\$str = \"$str\")']
    ],
    ["commonwhitelist",
        ["7638af69c9b6fee2e8bbeefe0d135b50a1f481062479d96932dec304d3e6acc1","0b219a59df1f8c50d0391d7c6bd6a31a0079627d7d171a93213200e2338635e1"],
        ['eval("$str)eval("\$str = \"$str\")','eval()eval("$str)eval("\$str = \"$str\")']
    ]
]
