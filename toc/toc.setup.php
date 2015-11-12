<?php
/* ====================
[BEGIN_COT_EXT]
Code=toc
Name=Table Of Contents
Description=Displays Table of contents of a given category
Version=1.0.1
Date=2012-06-04
Author=Trustmaster
Copyright=&copy; Vladimir Sibirov 2012
Notes=
SQL=
Auth_guests=R
Lock_guests=W12345A
Auth_members=R
Lock_members=W12345A
Requires_modules=page
[END_COT_EXT]

[BEGIN_COT_EXT_CONFIG]
sort_field=03:string::page_title:Sort by field
sort_way=04:select:desc,asc:asc:Sort order
cache=14:radio::1:Enable cache
[END_COT_EXT_CONFIG]
==================== */

defined('SED_CODE') or die('Wrong URL');
