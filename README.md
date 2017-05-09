# upv_mapping
php script for ingress damage report parsing, could use to create kml for google my map

# requirements
- PHP (crow used PHP 7, maybe PHP 5 works)
- know how to use gmail filters (which I use "subject:(Ingress Damage Report)") to group them and not let contamination affect our parsing
- know how to use google takeout (https://takeout.google.com/settings/takeout) to download your damage reports

# usage
1. sort damage report and download .mbox file from google takeout
2. replace file path, agent codename in report_parser.php and run the script
3. replace file path (if need) in portal_json_to_kml.php and run the script
4. import to google my map by kml

# note
Parsing .mbox file should not consume a lot of time, if it does, something is wrong.

In my case, my mail file was about 1.1G, I use Ubuntu 16.04 LTS (along with PHP 7.0.8) which was a virtualbox guest machine under windows 10 host; It will only need about 1 min to parse 100k mail record to gain about 12k portal data.

The parsing will give us a huge json string, you can use it at other places. It should contain: portal name, portal image url, lat, lng, geohash, attacker list, is upc or not.

# tips for importing to google my map
- Create all the blank layers you will need first (or you will get a lot of errors after importing one or two files and need to retry again and again.)
- Disable each layer and reload before you import next file.
- If google said error and need you to reload, just do it! Although you will need to do it again and again, the error was at google, not your kml file. You don't need to check your kml file, just upload again.
