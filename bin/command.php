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

$templateFile = dirname(__FILE__) . '/html/workshop1.html';
$tmpPath = dirname(__FILE__) . '/data';

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
    
    if (!is_readable($tmpPath)) {
        mkdir($tmpPath, 0777, true);
    }


    $mbox = imap_open("{{$server}/ssl/novalidate-cert}INBOX", $username, $password, null, 1, array('DISABLE_AUTHENTICATOR' => array('GSSAPI')));
    
    /* grab emails */
    $emails = imap_search($mbox,'ALL');
            
    $csvAttachments = array();
    if ($emails) {
        rsort($emails);
        /* for every email. Get csv Attachements.. */
        foreach($emails as $email_number) {
            $overview = imap_fetch_overview($mbox, $email_number);
            if (preg_match('/noreply@smartsparrow.com/i', $overview[0]->from) && preg_match('/Student Guest (.+) has finished Workshop Testing/i', $overview[0]->subject, $regs)) {
                $attachments = getAttachments   ($mbox, $email_number);
                foreach ($attachments as $file) {
                    if (preg_match('/\.csv$/i', $file['filename'])) {
                        $file['subject'] = $overview[0]->subject;
                        $csvAttachments[] = $file;
                    }
                }
            }
        }
    }
    imap_close($mbox);

    
    $emailList = array();
    $tmpPdf = tempnam ($tmpPath, 'pdf-').'.pdf';
    //$tmpPdf = 'doc.pdf';
    foreach($csvAttachments as $i => $csvFile) {
        
        $tmpCsv = tempnam ($tmpPath, 'csv-');
        file_put_contents($tmpCsv, $csvFile['attachment']);
        
        $template = \Dom\Template::loadFile($templateFile);
        $row = 0;
        if (($handle = fopen($tmpCsv, 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $row++;
                if ($row == 1) continue;
                $num = count($data);
                $template->appendHtml(str_replace(' ', '_', trim($data[3])),'<p>'. htmlentities($data[7]).'</p>');
                if (preg_match('/Email entry$/i', $data[3]) && filter_var(trim($data[7]), FILTER_VALIDATE_EMAIL)) {
                    $emailList[] = trim($data[7]);
                }
            }
            fclose($handle);
        }
        
        unlink($tmpCsv);
        
        $modifier = new \Tk\Dom\Modifier\Modifier();
        $modifier->add(new \App\DomModifier\ImageFilter($tmpPath . '/images'));
        $modifier->execute($template->getDocument());
        
        // instantiate and use the dompdf class
        $dompdf = new \Dompdf\Dompdf(array(
            'DOMPDF_ENABLE_REMOTE' => true,
            'DOMPDF_TEMP_DIR' => $tmpPath,
            'DEBUGKEEPTEMP' => true,
            'DEBUGPNG' => true
        ));
        $dompdf->loadHtml($template->toString());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        file_put_contents($tmpPdf, $pdf);
        
    }
    
    //var_dump($emailList);
    // Send emails with pdf attachment
    foreach($emailList as $e) {
        $e = 'mifsudm@unimelb.edu.au';
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        // Set PHPMailer to use the sendmail transport
        //$mail->isSendmail();
        $mail->isMail();
        //Set who the message is to be sent from
        $mail->setFrom('fvas-elearning@unimelb.edu.au', 'FVAS eLearning');
        //Set an alternative reply-to address
        //$mail->addReplyTo('replyto@example.com', 'First Last');
        //Set who the message is to be sent to
        $mail->addAddress($e);


        //Set the subject line
        $mail->Subject = 'PHPMailer sendmail test';
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $html = <<<HTML
<html>
<head>
  <title>Email</title>
</head>
<body>
  <h2>Workshop Group Answers</h2>
  <p>Check the attachment for your workshop group answers.</p>
  
  
  <p>This is a test</p>
  
  <h4>Can this also work</h4>
  
</body>
</html>
HTML;

        $mail->msgHTML($html);
        //Replace the plain text body with one created manually
        //$mail->AltBody = 'This is a plain-text message body';
        
        //Attach an image file
        $mail->addAttachment($tmpPdf, 'workshop.pdf');
        
        //send the message, check for errors
        if (!$mail->send()) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        } else {
            //echo "Message sent!";
        }
    }
    
    
    
    unlink($tmpPdf);
    // Delete Tmp path images dir.
    deleteDirContent($tmpPath);
        
    echo "\n";
} catch(\Exception $e) {
    die ($e->__toString());
}










function getAttachments($mbox, $email_number)
{
    $attachments = array();
    $structure = imap_fetchstructure($mbox, $email_number);
    if (isset($structure->parts) && count($structure->parts)) {
        for ($i = 0; $i < count($structure->parts); $i++) {

            $attachments[$i] = array(
                'is_attachment' => false,
                'filename' => '',
                'name' => '',
                'attachment' => ''
            );


            if ($structure->parts[$i]->ifdparameters) {
                foreach ($structure->parts[$i]->dparameters as $object) {
                    if (strtolower($object->attribute) == 'filename') {
                        $attachments[$i]['is_attachment'] = true;
                        $attachments[$i]['filename'] = $object->value;
                    }
                }
            }

            if ($structure->parts[$i]->ifparameters) {
                foreach ($structure->parts[$i]->parameters as $object) {
                    if (strtolower($object->attribute) == 'name') {
                        $attachments[$i]['is_attachment'] = true;
                        $attachments[$i]['name'] = $object->value;
                    }
                }
            }

            if ($attachments[$i]['is_attachment']) {
                $attachments[$i]['attachment'] = imap_fetchbody($mbox, $email_number, $i + 1);
                if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                    $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                } elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                    $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                }
            }
        }
    }
    return $attachments;
}


function deleteDirContent($path) {
    try{
        $iterator = new DirectoryIterator($path);
        foreach ( $iterator as $fileinfo ) {
            if($fileinfo->isDot())continue;
            if($fileinfo->isDir()){
                if(deleteDirContent($fileinfo->getPathname()))
                    @rmdir($fileinfo->getPathname());
            }
            if($fileinfo->isFile()){
                @unlink($fileinfo->getPathname());
            }
        }
    } catch ( Exception $e ){
        // write log
        return false;
    }
    return true;
}