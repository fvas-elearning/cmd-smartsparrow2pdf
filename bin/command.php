#!/usr/bin/php
<?php
/*
 * An executible for cron jobs related to the app
 *
 * # create nightly backup
 * 10  6,18  *   *   *      php /<fullProjectPath>/bin/cron
 *
 *
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2005 Michael Mifsud
 */
$sitePath = dirname(__DIR__);
$siteUrl = '/';
include($sitePath.'/vendor/autoload.php');
$config = \Tk\Config::getInstance();


$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];
$help = <<<TEXT
Usage: {$argv[0]}

  A description of the cli options and how to use the command.

  --help                Display this help text.
  -m={time}             max_execution_time value (Default: 0)

TEXT;

// CLI argument params
$args = array();
$minArgs = 1;
$maxArgs = 2;
$server= 'outlook.unimelb.edu.au';
$username = 'fvas-elearning@unimelb.edu.au';
$password = '';
$username = 'mifsudm@unimelb.edu.au';
$password = '`1`2qwer';


// Define any script parameter variables here
$maxExecutionTime = 0;

// Check max/min args
if ($argc < $minArgs || $argc > $maxArgs){
    echo $help;
    exit;
}
// Parse any script params from the command line
foreach ($argv as $param) {
    if (strtolower(substr($param, 0, 3)) == '-m=') {
        $maxExecutionTime = substr($param, 3);
        continue;
    }
    if (strtolower(substr($param, 0, 6)) == '--help') {
        echo $help;
        exit;
    }
    $args[] = $param;
}



try {
    // Write script code here
    ini_set('max_execution_time', $maxExecutionTime);
    //print_r(\Tk\Url::create('/Hello-World.html')->toString());


    $mbox = imap_open("{{$server}/ssl/novalidate-cert}INBOX", $username, $password, null, 1, array('DISABLE_AUTHENTICATOR' => array('GSSAPI')));
    
    /* grab emails */
    $emails = imap_search($mbox,'ALL');
            
    $csvAttachments = array();
    if ($emails) {
        rsort($emails);
        /* for every email... */
        foreach($emails as $email_number) {
            
            $structure = imap_fetchstructure($mbox, $email_number);
            if(isset($structure->parts) && count($structure->parts)) {
            
                $attachments = array();
                for($i = 0; $i < count($structure->parts); $i++) {
            
                    $attachments[$i] = array(
                        'is_attachment' => false,
                        'filename' => '',
                        'name' => '',
                        'attachment' => ''
                    );
                    
                    if($structure->parts[$i]->ifdparameters) {
                        foreach($structure->parts[$i]->dparameters as $object) {
                            if(strtolower($object->attribute) == 'filename') {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['filename'] = $object->value;
                            }
                        }
                    }
                    
                    if($structure->parts[$i]->ifparameters) {
                        foreach($structure->parts[$i]->parameters as $object) {
                            if(strtolower($object->attribute) == 'name') {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['name'] = $object->value;
                            }
                        }
                    }
                    
                    if($attachments[$i]['is_attachment']) {
                        $attachments[$i]['attachment'] = imap_fetchbody($mbox, $email_number, $i+1);
                        if($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                        }
                        elseif($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                        }
                    }
                    if (preg_match('/\.csv$/i', $attachments[$i]['filename'])) {
                        $csvAttachments[] = $attachments[$i];
                    }
                }
                
            }
            
        }
        
        
        var_dump($csvAttachments);
        
        
    }
       
    
    imap_close($mbox);
    
    
    
    
    
    echo "\n";
} catch(\Exception $e) {
    die ($e->toString());
}
exit;

