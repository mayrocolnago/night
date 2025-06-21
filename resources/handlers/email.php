<?php
class email {
    use \thread;
    
    private static $loaded = false; //default false always
    
    private static $credentials = [[
        'host' => 'smtp.gmail.com', // default smtp server
        'port' => '587', // default smtp port 
        'name' => null, // default sender name on email
        'from' => null, // default sender email same as sender
        'sender' => null, // default sender email same as from
        'user' => null, // can be the same configuration of sender
        'password' => null, // defaul email password auth
        'encryption' => 'tls' // default encryption type
    ]];

    public static function database() {
        return pdo_create("email_queue",[
            "id" => "int NOT NULL AUTO_INCREMENT",
            "email" => "varchar(200)",
            "subject" => "varchar(200) DEFAULT 'Novo email'",
            "body" => "longtext NULL DEFAULT NULL",
            "headers" => "longtext NULL DEFAULT NULL",
            "response" => "longtext NULL DEFAULT NULL",
            "sendat" => "int DEFAULT '0'"
        ]);
    }
    
    protected static function load() {
        if(self::$loaded) return true;
        if(is_array($sp = ($_SERVER["email"] ?? ($_SERVER["email_providers"] ?? '')))) self::$credentials = $sp;
        else foreach(self::$credentials[0] as $k => &$v)
                if(empty($v = ($_SERVER["email_$k"] ?? $v)))
                    if($k === 'password') return false;
        return (self::$loaded = true);
    }

    public function __construct() { return self::load(); }

    public static function send($email='',$subject='',$body='',$sendat=null,$headers=[],$queueonly=false) {
        if(($_SERVER['DEVELOPMENT'] ?? false) && is_bool($queueonly)) $queueonly = true;
        if(!empty($sendat) && ($queueonly === false)) $queueonly = true;
        if(!is_string($subject)) return -1;
        if(!is_string($body)) return -1;
        if(empty($sendat) || !is_numeric($sendat)) $sendat = strtotime('now');
        if(empty($subject = rmAentities(str_replace("'","",strip_tags(html_entity_decode($subject)))))) return -2;
        if(empty($body = rmAentities(str_replace('&amp;','&',str_replace("'","",$body))))) return -3;
        if(substr(($om=$body),0,3) == '<p>') //strpos($body,($tpltag='</tpl>')) !== false && !empty($om=str_replace($tpltag,'',$body)))
            if(file_exists($tpl=__DIR__.'/email.tpl') && !empty($body=@file_get_contents($tpl)))
                if(!empty($body = str_replace(($dc='<div id="content">'),"$dc$om",$body)))
                    if(!empty($body = str_replace('<code>','<p class="code" style="margin:32px 0px;background-color:rgba(255, 255, 255, 0.5);color:#00a551;padding:16px;font-weight:bold;font-size:18px;">',$body))) //mix-blend-mode:lighten;
                        $body = str_replace('</code>','</p>',$body);
        if(!empty(pdo_fetch_row("SELECT id FROM email_queue WHERE email='$email' AND sendat > ".strtotime('-1 minute')." AND sendat < ".strtotime('+1 minute')))) return 1.1;
        $result = pdo_insert('email_queue',['email'=>$email, 'subject'=>$subject, 'body'=>$body, 'sendat'=>$sendat, 'headers'=>$headers]);
        if(!$result && !is_array($queueonly)) return self::send($email,$subject,$body,$sendat,$headers,self::database());
        if(!$queueonly) self::async(function(){ \email::process_queue(['id'=>$result]); },[ 'result' => $result ]);
        return $result;
    }

    public static function process_queue($data=[], $log=[]) {
        //load module
        if(!self::load()) return false;
        //catch either the queue or a specific item
        if(!is_array($fila=pdo_fetch_array("SELECT * FROM email_queue  
            WHERE (response is null or response='')
                AND (".((!empty($id=preg_replace('/[^0-9]/','',($data['id'] ?? '')))) ? "id='$id'"
                  : "sendat < ".strtotime('now')).")
            ORDER BY sendat asc limit 20 ")) || empty($fila)) return -400;
        // start sending
        foreach($fila as $item)
            if(!empty($id=($item['id'] ?? ''))) {
                //log of every send
                $log[] = "[".str_maskmiddle(self::$credentials['sender'] ?? '')."@$id/email] processing...";
                if(empty($item['email'] ?? '') || empty($item['subject'] ?? '') || empty($item['body'] ?? ''))
                    if(pdo_query("UPDATE email_queue SET response='".json_encode(['result'=>'lack parameters'])."' WHERE id='$id' ") > -1) continue;
                //catches the job
                if(pdo_query("UPDATE email_queue SET response='".
                        json_encode(['result'=>'sending', 'date'=>date('Y-m-d H:i:s')])."'
                    WHERE id='$id' AND (response is null OR response='') ") > 0) {
                        //log sending
                        $log[] = "[$id/email] sending to ".str_maskmiddle($item['email'])."...";
                        //applies the answer
                        $log[] = "[$id/email] ".((pdo_query("UPDATE email_queue SET response=:r WHERE id='$id' ",[
                            'r' => ['response'=>self::api($item['email'], $item['subject'], $item['body'], ($item['headers'] ?? []))]
                        ])) ? 'sent' : 'error').".";
                } else
                  $log[] = "[$id/email] job already taken.";
            }
        return $log;
    }

    protected static function api($to,$subject,$msg,$extra=[]) {
        //load module
        if(!self::load()) return false;
        //validate parameters
        $retry = 0;
        $success = false;
        $responses = [];
        $credentials = ((is_array(self::$credentials[0] ?? '')) ? self::$credentials : [self::$credentials]);
        //iterate through credentials
        foreach($credentials as $credential)
            if(!empty($method = ((($credential['user'] ?? '') === 'apikey') ? 'sendgrid' : 'smtp'))) {
                $res=call_user_func_array("self::$method",[$to,$subject,$msg,$extra,$credential]);
                $responses[] = $res; $retry++;
                if($success = ($res['result'] ?? false)) break; }
        return [
            'result' => (($success) ? $retry : 'all methods failed'),
            'credentials' => ((empty($responses)) ? str_maskmiddle_array($credentials) : null),
            'responses' => $responses
        ];
    }

    protected static function sendgrid($to,$subject,$msg,$extra=[],$credentials=null) {
        //load module
        if(!self::load()) return false;
        //validate parameters
        $from = ($extra['from'] ?? ($credentials['from'] ?? ($credentials['sender'] ?? ($credentials['user'] ?? ''))));
        $api_key = ($extra['pass'] ?? ($extra['apikey'] ?? ($credentials['password'] ?? ($credentials['passw'] ?? ($credentials['pass'] ?? ($credentials['apikey'] ?? ''))))));
        //sendgrid curl
        @ob_start();
        $url = 'https://api.sendgrid.com/v3/mail/send';
        $data = [
            'personalizations' => [
                [ 'to' => [ ['email' => $to] ] ]
            ],
            'from' => [ 'email' => $from ],
            'subject' => html_entity_decode($subject),
            'content' => [
                [ 'type' => 'text/plain', 'value' => html_entity_decode(strip_tags($msg)) ],
                [ 'type' => 'text/html', 'value' => $msg ]
            ] ];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if(curl_error($ch))
            echo 'Erro cURL: ' . curl_error($ch);
        else
            echo 'Status HTTP: ' . $http_code . PHP_EOL .
                 'Response: ' . $response . PHP_EOL;
        curl_close($ch);
        $_SERVER['email_response'] = str_replace(["'",'"'],'::',@ob_get_contents());
        @ob_end_clean();
        //return result
        return [
            'result' => (intval($http_code ?? 0) === 202),
            'credentials' => str_maskmiddle_array($credentials),
            'response' => emojientities($_SERVER['email_response'] ?? '')
        ];
    }

    protected static function smtp($to,$subject,$msg,$extra=[],$credentials=null) {
        //load module
        if(!self::load()) return false;
        //validate parameters
        $email = ($extra['from'] ?? ($credentials['from'] ?? ($credentials['sender'] ?? ($credentials['user'] ?? ''))));
        $user = ($extra['user'] ?? ($credentials['user'] ?? ($credentials['sender'] ?? ($credentials['from'] ?? ''))));
        $pass = ($extra['pass'] ?? ($credentials['password'] ?? ($credentials['passw'] ?? ($credentials['pass'] ?? ''))));
        $nome = ($extra['nome'] ?? ($extra['name'] ?? ($credentials['name'] ?? '')));
        //start sending and loggin
        @ob_start();
        $_SERVER['email_response'] = '';
        $mail = new PHPMailer(false); try { //true for exceptions
            //validate parameters
            if((!(strpos($email,'@') !== false)) && strpos($nome,'@') !== false) 
                if(!empty($email = $nome) && is_array($nome = explode('@',$nome)))
                    $nome = strtoupper(explode('.',$nome[1].'.')[0]);
            //Server settings
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host = ($extra['host'] ?? ($credentials['host'] ?? '')); // Specify main and backup SMTP servers
            $mail->Port = ($extra['port'] ?? ($credentials['port'] ?? 587));  // TCP port to connect to
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = $user; // SMTP username
            $mail->Password = $pass; // SMTP password
            //encryption configuration
            if(in_array(($enclayer=preg_replace('/[^a-z]/','',(strtolower($extra['secure'] ?? ($extra['encryption'] ?? ($credentials['encryption'] ?? 'tls')))))),
            ['tls', 'ssl'])) $mail->SMTPSecure = $enclayer; // Enable TLS encryption, `ssl` also accepted
            else $mail->SMTPSecure = 'tls'; //default to tls
            //Recipients
            $mail->setFrom($email, $nome);
            //To address validation
            if(strpos($to,',') !== false || strpos($to,';') !== false) $to = explode(';',str_replace(',',';',$to));
            if(!is_array($to)) $mail->addAddress($to);
            else foreach($to as $item) $mail->addAddress($item);
            //Configure ReplyTo
            if(!empty($extra['replyto'] ?? '')) $mail->addReplyTo($extra['replyto']);
            //Configure Copy email to
            if(!empty($cc=($extra['cc'] ?? ''))) {
                if(strpos($cc,',') !== false || strpos($cc,';') !== false) $cc = explode(';',str_replace(',',';',$cc));
                if(!is_array($cc)) $mail->addCC($cc);
                else foreach($cc as $item) if(!empty(trim($item))) $mail->addCC($item); }
            //Configure Carbon Copy
            if(!empty($bcc=($extra['bcc'] ?? ''))) {
                if(strpos($bcc,',') !== false || strpos($bcc,';') !== false) $bcc = explode(';',str_replace(',',';',$bcc));
                if(!is_array($bcc)) $mail->addBCC($bcc);
                else foreach($bcc as $item) if(!empty(trim($item))) $mail->addBCC($item); }
            //Attachments logic
            if(!empty($attach = ($extra['attach'] ?? '')))
                if(!is_array($attach)) $mail->addAttachment($attach);
                else foreach($attach as $item) if(!empty(trim($item))) $mail->addAttachment($item);
            //Content
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = utf8_decode(html_entity_decode($subject));
            $mail->Body    = utf8_decode($msg); //manter html
            $mail->AltBody = strip_tags(str_ireplace('<br>',chr(13),utf8_decode(html_entity_decode($msg))));
            //Send email
            $sent = $mail->send();
            var_dump('SENT',$sent);
        } catch (Exception $e) { 
            var_dump('ERROR',$e); 
            $sent = false; }
        //Log generator
        $_SERVER['email_response'] = str_replace(["'",'"'],'::',@ob_get_contents());
        if(strpos(strtoupper($_SERVER['email_response']),'SMTP ERROR') !== false)
            if(strpos(strtolower($_SERVER['email_response']),'failed') !== false) $sent = false;
        @ob_end_clean();
        //return result
        return [
            'result' => $sent, 
            'credentials' => str_maskmiddle_array($credentials),
            'response' => emojientities($_SERVER['email_response'] ?? '')
        ];
    }
}
 
//  * PHPMailer RFC821 SMTP email transport class.
//  * PHP Version 5.5.
//  *
//  * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
//  *
//  * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
//  * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
//  * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
//  * @author    Brent R. Matzelle (original founder)
//  * @copyright 2012 - 2017 Marcus Bointon
//  * @copyright 2010 - 2012 Jim Jagielski
//  * @copyright 2004 - 2009 Andy Prevost
//  * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
//  * @note      This program is distributed in the hope that it will be useful - WITHOUT
//  * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
//  * FITNESS FOR A PARTICULAR PURPOSE.
//  *
//  *
 
//  * PHPMailer RFC821 SMTP email transport class.
//  * Implements RFC 821 SMTP commands and provides some utility methods for sending mail to an SMTP server.
//  *
//  * @author  Chris Ryan
//  * @author  Marcus Bointon <phpmailer@synchromedia.co.uk>
//  *

class SMTP {
    const VERSION = '6.0.5';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;
    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    protected $smtp_transaction_id_patterns = [
        'exim' => '/[0-9]{3} OK id=(.*)/',
        'sendmail' => '/[0-9]{3} 2.0.0 (.*) Message/',
        'postfix' => '/[0-9]{3} 2.0.0 Ok: queued as (.*)/',
        'Microsoft_ESMTP' => '/[0-9]{3} 2.[0-9].0 (.*)@(?:.*) Queued mail for delivery/',
        'Amazon_SES' => '/[0-9]{3} Ok (.*)/',
        'SendGrid' => '/[0-9]{3} Ok: queued as (.*)/',
    ];
    protected $last_smtp_transaction_id;
    protected $smtp_conn;
    protected $error = [
        'error' => '',
        'detail' => '',
        'smtp_code' => '',
        'smtp_code_ex' => '',
    ];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';
 
    protected function edebug($str, $level = 0) {
        if ($level > $this->do_debug) {
            return;
        }
        //Is this a PSR-3 logger?
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        //Avoid clash with built-in function names
        if (!in_array($this->Debugoutput, ['error_log', 'html', 'echo']) and is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                //Don't output, just log
                error_log($str);
                break;
            case 'html':
                //Cleans up output a bit for a better looking, HTML-safe output
                echo gmdate('Y-m-d H:i:s'), ' ', htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ), "<br>\n";
                break;
            case 'echo':
            default:
                //Normalize line breaks
                $str = preg_replace('/\r\n|\r/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s'),
                "\t",
                    //Trim trailing space
                trim(
                //Indent for readability, except for trailing break
                    str_replace(
                        "\n",
                        "\n                   \t                  ",
                        trim($str)
                    )
                ),
                "\n";
        }
    }
 
    public function connect($host, $port = null, $timeout = 30, $options = []) {
        static $streamok;
        //This is enabled by default since 5.0.0 but some providers disable it
        //Check this once and cache the result
        if (null === $streamok) {
            $streamok = function_exists('stream_socket_client');
        }
        // Clear errors to avoid confusion
        $this->setError('');
        // Make sure we are __not__ connected
        if ($this->connected()) {
            // Already connected, generate error
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        // Connect to the SMTP server
        $this->edebug(
            "Connection: opening to $host:$port, timeout=$timeout, options=" .
            (count($options) > 0 ? var_export($options, true) : 'array()'),
            self::DEBUG_CONNECTION
        );
        $errno = 0;
        $errstr = '';
        if ($streamok) {
            $socket_context = stream_context_create($options);
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = stream_socket_client(
                $host . ':' . $port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
            restore_error_handler();
        } else {
            //Fall back to fsockopen which should work in more places, but is missing some features
            $this->edebug(
                'Connection: stream_socket_client not available, falling back to fsockopen',
                self::DEBUG_CONNECTION
            );
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = fsockopen(
                $host,
                $port,
                $errno,
                $errstr,
                $timeout
            );
            restore_error_handler();
        }
        // Verify we connected properly
        if (!is_resource($this->smtp_conn)) {
            $this->setError(
                'Failed to connect to server',
                '',
                (string) $errno,
                (string) $errstr
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error']
                . ": $errstr ($errno)",
                self::DEBUG_CLIENT
            );
            return false;
        }
        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);
        // SMTP server can take longer to respond, give longer timeout for first read
        // Windows does not have support for this timeout function
        if (substr(PHP_OS, 0, 3) != 'WIN') {
            $max = ini_get('max_execution_time');
            // Don't bother if unlimited
            if (0 != $max and $timeout > $max) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($this->smtp_conn, $timeout, 0);
        }
        // Get any announcement
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
        return true;
    }
 
    public function startTLS() {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        //Allow the best TLS version(s) we can
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        //PHP 5.6.7 dropped inclusion of TLS 1.1 and 1.2 in STREAM_CRYPTO_METHOD_TLS_CLIENT
        //so add them back in manually if we can
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        // Begin encrypted connection
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto(
            $this->smtp_conn,
            true,
            $crypto_method
        );
        restore_error_handler();
        return (bool) $crypto_ok;
    }
    public function authenticate(
        $username,
        $password,
        $authtype = null,
        $OAuth = null
    ) {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');
            return false;
        }
        if (array_key_exists('EHLO', $this->server_caps)) {
            // SMTP extensions are available; try to find a proper authentication method
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Authentication is not allowed at this stage');
                // 'at this stage' means that auth may be allowed after the stage changes
                // e.g. after STARTTLS
                return false;
            }
            $this->edebug('Auth method requested: ' . ($authtype ? $authtype : 'UNSPECIFIED'), self::DEBUG_LOWLEVEL);
            $this->edebug(
                'Auth methods available on the server: ' . implode(',', $this->server_caps['AUTH']),
                self::DEBUG_LOWLEVEL
            );
            //If we have requested a specific auth type, check the server supports it before trying others
            if (null !== $authtype and !in_array($authtype, $this->server_caps['AUTH'])) {
                $this->edebug('Requested auth method not available: ' . $authtype, self::DEBUG_LOWLEVEL);
                $authtype = null;
            }
            if (empty($authtype)) {
                //If no auth mechanism is specified, attempt to use these, in this order
                //Try CRAM-MD5 first as it's more secure than the others
                foreach (['CRAM-MD5', 'LOGIN', 'PLAIN', 'XOAUTH2'] as $method) {
                    if (in_array($method, $this->server_caps['AUTH'])) {
                        $authtype = $method;
                        break;
                    }
                }
                if (empty($authtype)) {
                    $this->setError('No supported authentication methods found');
                    return false;
                }
                self::edebug('Auth method selected: ' . $authtype, self::DEBUG_LOWLEVEL);
            }
            if (!in_array($authtype, $this->server_caps['AUTH'])) {
                $this->setError("The requested authentication method \"$authtype\" is not supported by the server");
                return false;
            }
        } elseif (empty($authtype)) {
            $authtype = 'LOGIN';
        }
        switch ($authtype) {
            case 'PLAIN':
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                // Send encoded username and password
                if (!$this->sendCommand(
                    'User & Password',
                    base64_encode("\0" . $username . "\0" . $password),
                    235
                )
                ) {
                    return false;
                }
                break;
            case 'LOGIN':
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                // Start authentication
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                // Get the challenge
                $challenge = base64_decode(substr($this->last_reply, 4));
                // Build the response
                $response = $username . ' ' . $this->hmac($challenge, $password);
                // send encoded credentials
                return $this->sendCommand('Username', base64_encode($response), 235);
            case 'XOAUTH2':
                //The OAuth instance must be set up prior to requesting auth.
                if (null === $OAuth) {
                    return false;
                }
                $oauth = $OAuth->getOauth64();
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH XOAUTH2 ' . $oauth, 235)) {
                    return false;
                }
                break;
            default:
                $this->setError("Authentication method \"$authtype\" is not supported");
                return false;
        }
        return true;
    }
 
    protected function hmac($data, $key) {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }
        // The following borrowed from
        // http://php.net/manual/en/function.mhash.php#27225
        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // by Lance Rushing
        $bytelen = 64; // byte length for md5
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key = str_pad($key, $bytelen, chr(0x00));
        $ipad = str_pad('', $bytelen, chr(0x36));
        $opad = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }
 
    public function connected() {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                // The socket is valid but we are not connected
                $this->edebug(
                    'SMTP NOTICE: EOF caught while checking if connected',
                    self::DEBUG_CLIENT
                );
                $this->close();
                return false;
            }
            return true; // everything looks good
        }
        return false;
    }
 
    public function close() {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            // close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = null; //Makes for cleaner serialization
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }
 
    public function data($msg_data) {
        //This will use the standard timelimit
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        // Normalize line breaks before exploding
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) and strpos($field, ' ') === false) {
            $in_headers = true;
        }
        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers and $line == '') {
                $in_headers = false;
            }
            //Break this line up into several smaller lines if it's too long
            //Micro-optimisation: isset($str[$len]) is faster than (strlen($str) > $len),
            while (isset($line[self::MAX_LINE_LENGTH])) {
                //Working backwards, try to find a space within the last MAX_LINE_LENGTH chars of the line to break on
                //so as to avoid breaking in the middle of a word
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                //Deliberately matches both false and 0
                if (!$pos) {
                    //No nice break found, add a hard break
                    $pos = self::MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    //Break at the found point
                    $lines_out[] = substr($line, 0, $pos);
                    //Move along by the amount we dealt with
                    $line = substr($line, $pos + 1);
                }
                //If processing headers add a LWSP-char to the front of new line RFC822 section 3.1.1
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;
            //Send the lines to the server
            foreach ($lines_out as $line_out) {
                //RFC2821 section 4.5.2
                if (!empty($line_out) and $line_out[0] == '.') {
                    $line_out = '.' . $line_out;
                }
                $this->client_send($line_out . static::LE, 'DATA');
            }
        }
        //Message data has been sent, complete the command
        //Increase timelimit for end of DATA command
        $savetimelimit = $this->Timelimit;
        $this->Timelimit = $this->Timelimit * 2;
        $result = $this->sendCommand('DATA END', '.', 250);
        $this->recordLastTransactionID();
        //Restore timelimit
        $this->Timelimit = $savetimelimit;
        return $result;
    }
 
    public function hello($host = '') {
        //Try extended hello first (RFC 2821)
        return (bool) ($this->sendHello('EHLO', $host) or $this->sendHello('HELO', $host));
    }
 
    protected function sendHello($hello, $host) {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }
        return $noerror;
    }
 
    protected function parseHelloFields($type) {
        $this->server_caps = [];
        $lines = explode("\n", $this->helo_rply);
        foreach ($lines as $n => $s) {
            //First 4 chars contain response code followed by - or space
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $type;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                    switch ($name) {
                        case 'SIZE':
                            $fields = ($fields ? $fields[0] : 0);
                            break;
                        case 'AUTH':
                            if (!is_array($fields)) {
                                $fields = [];
                            }
                            break;
                        default:
                            $fields = true;
                    }
                }
                $this->server_caps[$name] = $fields;
            }
        }
    }
 
    public function mail($from) {
        $useVerp = ($this->do_verp ? ' XVERP' : '');
        return $this->sendCommand(
            'MAIL FROM',
            'MAIL FROM:<' . $from . '>' . $useVerp,
            250
        );
    }
 
    public function quit($close_on_error = true) {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error; //Save any error
        if ($noerror or $close_on_error) {
            $this->close();
            $this->error = $err; //Restore any error from the quit command
        }
        return $noerror;
    }
 
    public function recipient($address) {
        return $this->sendCommand(
            'RCPT TO',
            'RCPT TO:<' . $address . '>',
            [250, 251]
        );
    }
 
    public function reset() {
        return $this->sendCommand('RSET', 'RSET', 250);
    }
 
    protected function sendCommand($command, $commandstring, $expect) {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");
            return false;
        }
        //Reject line breaks in all commands
        if (strpos($commandstring, "\n") !== false or strpos($commandstring, "\r") !== false) {
            $this->setError("Command '$command' contained line breaks");
            return false;
        }
        $this->client_send($commandstring . static::LE, $command);
        $this->last_reply = $this->get_lines();
        // Fetch SMTP code and possible error code explanation
        $matches = [];
        if (preg_match('/^([0-9]{3})[ -](?:([0-9]\\.[0-9]\\.[0-9]) )?/', $this->last_reply, $matches)) {
            $code = $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            // Cut off error code from each response line
            $detail = preg_replace(
                "/{$code}[ -]" .
                ($code_ex ? str_replace('.', '\\.', $code_ex) . ' ' : '') . '/m',
                '',
                $this->last_reply
            );
        } else {
            // Fall back to simple parsing if regex fails
            $code = substr($this->last_reply, 0, 3);
            $code_ex = null;
            $detail = substr($this->last_reply, 4);
        }
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);
        if (!in_array($code, (array) $expect)) {
            $this->setError(
                "$command command failed",
                $detail,
                $code,
                $code_ex
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply,
                self::DEBUG_CLIENT
            );
            return false;
        }
        $this->setError('');
        return true;
    }
 
    public function sendAndMail($from) {
        return $this->sendCommand('SAML', "SAML FROM:$from", 250);
    }
 
    public function verify($name) {
        return $this->sendCommand('VRFY', "VRFY $name", [250, 251]);
    }
 
    public function noop() {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }
 
    public function turn() {
        $this->setError('The SMTP TURN command is not implemented');
        $this->edebug('SMTP NOTICE: ' . $this->error['error'], self::DEBUG_CLIENT);
        return false;
    }
 
    public function client_send($data, $command = '') {
        //If SMTP transcripts are left enabled, or debug output is posted online
        //it can leak credentials, so hide credentials in all but lowest level
        if (self::DEBUG_LOWLEVEL > $this->do_debug and
            in_array($command, ['User & Password', 'Username', 'Password'], true)) {
            $this->edebug('CLIENT -> SERVER: <credentials hidden>', self::DEBUG_CLIENT);
        } else {
            $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);
        }
        set_error_handler([$this, 'errorHandler']);
        $result = fwrite($this->smtp_conn, $data);
        restore_error_handler();
        return $result;
    }
 
    public function getError() {
        return $this->error;
    }
 
    public function getServerExtList() {
        return $this->server_caps;
    }
 
    public function getServerExt($name) {
        if (!$this->server_caps) {
            $this->setError('No HELO/EHLO was sent');
            return;
        }
        if (!array_key_exists($name, $this->server_caps)) {
            if ('HELO' == $name) {
                return $this->server_caps['EHLO'];
            }
            if ('EHLO' == $name || array_key_exists('EHLO', $this->server_caps)) {
                return false;
            }
            $this->setError('HELO handshake was used; No information about server extensions available');
            return;
        }
        return $this->server_caps[$name];
    }
 
    public function getLastReply() {
        return $this->last_reply;
    }
 
    protected function get_lines() {
        // If the connection is bad, give up straight away
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        $selR = [$this->smtp_conn];
        $selW = null;
        while (is_resource($this->smtp_conn) and !feof($this->smtp_conn)) {
            //Must pass vars in here as params are by reference
            if (!stream_select($selR, $selW, $selW, $this->Timelimit)) {
                $this->edebug(
                    'SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
            //Deliberate noise suppression - errors are handled afterwards
            $str = @fgets($this->smtp_conn, 515);
            $this->edebug('SMTP INBOUND: "' . trim($str) . '"', self::DEBUG_LOWLEVEL);
            $data .= $str;
            // If response is only 3 chars (not valid, but RFC5321 S4.2 says it must be handled),
            // or 4th character is a space, we are done reading, break the loop,
            // string array access is a micro-optimisation over strlen
            if (!isset($str[3]) or (isset($str[3]) and $str[3] == ' ')) {
                break;
            }
            // Timed-out? Log and break
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug(
                    'SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
            // Now check if reads took too long
            if ($endtime and time() > $endtime) {
                $this->edebug(
                    'SMTP -> get_lines(): timelimit reached (' .
                    $this->Timelimit . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
        }
        return $data;
    }
 
    public function setVerp($enabled = false) {
        $this->do_verp = $enabled;
    }
 
    public function getVerp() {
        return $this->do_verp;
    }
 
    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '') {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }
 
    public function setDebugOutput($method = 'echo') {
        $this->Debugoutput = $method;
    }
 
    public function getDebugOutput() {
        return $this->Debugoutput;
    }
 
    public function setDebugLevel($level = 0) {
        $this->do_debug = $level;
    }
 
    public function getDebugLevel() {
        return $this->do_debug;
    }
 
    public function setTimeout($timeout = 0) {
        $this->Timeout = $timeout;
    }
 
    public function getTimeout() {
        return $this->Timeout;
    }
 
    protected function errorHandler($errno, $errmsg, $errfile = '', $errline = 0) {
        $notice = 'Connection failed.';
        $this->setError(
            $notice,
            $errmsg,
            (string) $errno
        );
        $this->edebug(
            "$notice Error #$errno: $errmsg [$errfile line $errline]",
            self::DEBUG_CONNECTION
        );
    }
 
    protected function recordLastTransactionID() {
        $reply = $this->getLastReply();
        if (empty($reply)) {
            $this->last_smtp_transaction_id = null;
        } else {
            $this->last_smtp_transaction_id = false;
            foreach ($this->smtp_transaction_id_patterns as $smtp_transaction_id_pattern) {
                if (preg_match($smtp_transaction_id_pattern, $reply, $matches)) {
                    $this->last_smtp_transaction_id = trim($matches[1]);
                    break;
                }
            }
        }
        return $this->last_smtp_transaction_id;
    }
 
    public function getLastTransactionID() {
        return $this->last_smtp_transaction_id;
    }
}
 
 
class PHPMailer {
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    public $Priority;
    public $CharSet = self::CHARSET_ISO88591;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = false;
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    protected $oauth;
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    protected $SingleToArray = [];
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    public static $validator = 'php';
    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $uniqueid = '';
    const VERSION = '6.0.5';
    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;
    protected static $LE = "\r\n";
    const MAX_LINE_LENGTH = 998;
    const STD_LINE_LENGTH = 76;
 
    public function __construct($exceptions = null) {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
        //Pick an appropriate debug output format automatically
        $this->Debugoutput = (strpos(PHP_SAPI, 'cli') !== false ? 'echo' : 'html');
    }
 
    public function __destruct() {
        //Close any open SMTP connection nicely
        $this->smtpClose();
    }
 
    private function mailPassthru($to, $subject, $body, $header, $params) {
        //Check overloading of mail function to avoid double-encoding
        if (ini_get('mbstring.func_overload') & 1) {
            $subject = $this->secureHeader($subject);
        } else {
            $subject = $this->encodeHeader($this->secureHeader($subject));
        }
        //Calling mail() with null params breaks
        if (!$this->UseSendmailOptions or null === $params) {
            $result = @mail($to, $subject, $body, $header);
        } else {
            $result = @mail($to, $subject, $body, $header, $params);
        }
        return $result;
    }
 
    protected function edebug($str) {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        //Is this a PSR-3 logger?
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        //Avoid clash with built-in function names
        if (!in_array($this->Debugoutput, ['error_log', 'html', 'echo']) and is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                //Don't output, just log
                error_log($str);
                break;
            case 'html':
                //Cleans up output a bit for a better looking, HTML-safe output
                echo htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ), "<br>\n";
                break;
            case 'echo':
            default:
                //Normalize line breaks
                $str = preg_replace('/\r\n|\r/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s'),
                "\t",
                    //Trim trailing space
                trim(
                //Indent for readability, except for trailing break
                    str_replace(
                        "\n",
                        "\n                   \t                  ",
                        trim($str)
                    )
                ),
                "\n";
        }
    }
 
    public function isHTML($isHtml = true) {
        if ($isHtml) {
            $this->ContentType = static::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = static::CONTENT_TYPE_PLAINTEXT;
        }
    }
 
    public function isSMTP() {
        $this->Mailer = 'smtp';
    }
 
    public function isMail() {
        $this->Mailer = 'mail';
    }
 
    public function isSendmail() {
        $ini_sendmail_path = ini_get('sendmail_path');
        if (false === stripos($ini_sendmail_path, 'sendmail')) {
            $this->Sendmail = '/usr/sbin/sendmail';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'sendmail';
    }
 
    public function isQmail() {
        $ini_sendmail_path = ini_get('sendmail_path');
        if (false === stripos($ini_sendmail_path, 'qmail')) {
            $this->Sendmail = '/var/qmail/bin/qmail-inject';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'qmail';
    }
 
    public function addAddress($address, $name = '') {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }
 
    public function addCC($address, $name = '') {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }
 
    public function addBCC($address, $name = '') {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }
 
    public function addReplyTo($address, $name = '') {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }
 
    protected function addOrEnqueueAnAddress($kind, $address, $name) {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        $pos = strrpos($address, '@');
        if (false === $pos) {
            // At-sign is missing.
            $error_message = sprintf('%s (%s): %s',
                $this->lang('invalid_address'),
                $kind,
                $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                var_dump($error_message);
            }
            return false;
        }
        $params = [$kind, $address, $name];
        // Enqueue addresses with IDN until we know the PHPMailer::$CharSet.
        if ($this->has8bitChars(substr($address, ++$pos)) and static::idnSupported()) {
            if ('Reply-To' != $kind) {
                if (!array_key_exists($address, $this->RecipientsQueue)) {
                    $this->RecipientsQueue[$address] = $params;
                    return true;
                }
            } else {
                if (!array_key_exists($address, $this->ReplyToQueue)) {
                    $this->ReplyToQueue[$address] = $params;
                    return true;
                }
            }
            return false;
        }
        // Immediately add standard addresses without IDN.
        return call_user_func_array([$this, 'addAnAddress'], $params);
    }
 
    protected function addAnAddress($kind, $address, $name = '') {
        if (!in_array($kind, ['to', 'cc', 'bcc', 'Reply-To'])) {
            $error_message = sprintf('%s: %s',
                $this->lang('Invalid recipient kind'),
                $kind);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                var_dump($error_message);
            }
            return false;
        }
        if (!static::validateAddress($address)) {
            $error_message = sprintf('%s (%s): %s',
                $this->lang('invalid_address'),
                $kind,
                $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                var_dump($error_message);
            }
            return false;
        }
        if ('Reply-To' != $kind) {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];
                return true;
            }
        }
        return false;
    }
 
    public static function parseAddresses($addrstr, $useimap = true) {
        $addresses = [];
        if ($useimap and function_exists('imap_rfc822_parse_adrlist')) {
            //Use this built-in parser if it's available
            $list = imap_rfc822_parse_adrlist($addrstr, '');
            foreach ($list as $address) {
                if ('.SYNTAX-ERROR.' != $address->host) {
                    if (static::validateAddress($address->mailbox . '@' . $address->host)) {
                        $addresses[] = [
                            'name' => (property_exists($address, 'personal') ? $address->personal : ''),
                            'address' => $address->mailbox . '@' . $address->host,
                        ];
                    }
                }
            }
        } else {
            //Use this simpler parser
            $list = explode(',', $addrstr);
            foreach ($list as $address) {
                $address = trim($address);
                //Is there a separate name part?
                if (strpos($address, '<') === false) {
                    //No separate name, just use the whole thing
                    if (static::validateAddress($address)) {
                        $addresses[] = [
                            'name' => '',
                            'address' => $address,
                        ];
                    }
                } else {
                    list($name, $email) = explode('<', $address);
                    $email = trim(str_replace('>', '', $email));
                    if (static::validateAddress($email)) {
                        $addresses[] = [
                            'name' => trim(str_replace(['"', "'"], '', $name)),
                            'address' => $email,
                        ];
                    }
                }
            }
        }
        return $addresses;
    }
 
    public function setFrom($address, $name = '', $auto = true) {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        // Don't validate now addresses with IDN. Will be done in send().
        $pos = strrpos($address, '@');
        if (false === $pos or
            (!$this->has8bitChars(substr($address, ++$pos)) or !static::idnSupported()) and
            !static::validateAddress($address)) {
            $error_message = sprintf('%s (From): %s',
                $this->lang('invalid_address'),
                $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                var_dump($error_message);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }
        return true;
    }
 
    public function getLastMessageID() {
        return $this->lastMessageID;
    }
 
    public static function validateAddress($address, $patternselect = null) {
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        if (is_callable($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        //Reject line breaks in addresses; it's valid RFC5322, but not RFC5321
        if (strpos($address, "\n") !== false or strpos($address, "\r") !== false) {
            return false;
        }
        switch ($patternselect) {
            case 'pcre': //Kept for BC
            case 'pcre8':
                return (bool) preg_match(
                    '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)' .
                    '((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)' .
                    '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)' .
                    '([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*' .
                    '(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z0-9-]{64,})(?1)(?>([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)' .
                    '(?>(?1)\.(?!(?1)[a-z0-9-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f0-9]{1,4})(?>:(?6)){7}' .
                    '|(?!(?:.*[a-f0-9][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:' .
                    '|(?!(?:.*[a-f0-9]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
                    '|[1-9]?[0-9])(?>\.(?9)){3}))\])(?1)$/isD',
                    $address
                );
            case 'html5':
                return (bool) preg_match(
                    '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}' .
                    '[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD',
                    $address
                );
            case 'php':
            default:
                return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
        }
    }
 
    public static function idnSupported() {
        return function_exists('idn_to_ascii') and function_exists('mb_convert_encoding');
    }
 
    public function punyencodeAddress($address) {
        // Verify we have required functions, CharSet, and at-sign.
        $pos = strrpos($address, '@');
        if (static::idnSupported() and
            !empty($this->CharSet) and
            false !== $pos
        ) {
            $domain = substr($address, ++$pos);
            // Verify CharSet string is a valid one, and domain properly encoded in this CharSet.
            if ($this->has8bitChars($domain) and @mb_check_encoding($domain, $this->CharSet)) {
                $domain = mb_convert_encoding($domain, 'UTF-8', $this->CharSet);
                //Ignore IDE complaints about this line - method signature changed in PHP 5.4
                $errorcode = 0;
                $punycode = idn_to_ascii($domain, $errorcode, INTL_IDNA_VARIANT_UTS46);
                if (false !== $punycode) {
                    return substr($address, 0, $pos) . $punycode;
                }
            }
        }
        return $address;
    }
 
    public function send() {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }
 
    public function preSend() {
        if ('smtp' == $this->Mailer or
            ('mail' == $this->Mailer and stripos(PHP_OS, 'WIN') === 0)
        ) {
            //SMTP mandates RFC-compliant line endings
            //and it's also used with mail() on Windows
            static::setLE("\r\n");
        } else {
            //Maintain backward compatibility with legacy Linux command line mailers
            static::setLE(PHP_EOL);
        }
        //Check for buggy PHP versions that add a header with an incorrect line break
        if (ini_get('mail.add_x_header') == 1
            and 'mail' == $this->Mailer
            and stripos(PHP_OS, 'WIN') === 0
            and ((version_compare(PHP_VERSION, '7.0.0', '>=')
                    and version_compare(PHP_VERSION, '7.0.17', '<'))
                or (version_compare(PHP_VERSION, '7.1.0', '>=')
                    and version_compare(PHP_VERSION, '7.1.3', '<')))
        ) {
            trigger_error(
                'Your version of PHP is affected by a bug that may result in corrupted messages.' .
                ' To fix it, switch to sending using SMTP, disable the mail.add_x_header option in' .
                ' your php.ini, switch to MacOS or Linux, or upgrade your PHP to version 7.0.17+ or 7.1.3+.',
                E_USER_WARNING
            );
        }
        try {
            $this->error_count = 0; // Reset errors
            $this->mailHeader = '';
            // Dequeue recipient and Reply-To addresses with IDN
            foreach (array_merge($this->RecipientsQueue, $this->ReplyToQueue) as $params) {
                $params[1] = $this->punyencodeAddress($params[1]);
                call_user_func_array([$this, 'addAnAddress'], $params);
            }
            if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
                var_dump($this->lang('provide_address'), self::STOP_CRITICAL);
            }
            // Validate From, Sender, and ConfirmReadingTo addresses
            foreach (['From', 'Sender', 'ConfirmReadingTo'] as $address_kind) {
                $this->$address_kind = trim($this->$address_kind);
                if (empty($this->$address_kind)) {
                    continue;
                }
                $this->$address_kind = $this->punyencodeAddress($this->$address_kind);
                if (!static::validateAddress($this->$address_kind)) {
                    $error_message = sprintf('%s (%s): %s',
                        $this->lang('invalid_address'),
                        $address_kind,
                        $this->$address_kind);
                    $this->setError($error_message);
                    $this->edebug($error_message);
                    if ($this->exceptions) {
                        var_dump($error_message);
                    }
                    return false;
                }
            }
            // Set whether the message is multipart/alternative
            if ($this->alternativeExists()) {
                $this->ContentType = static::CONTENT_TYPE_MULTIPART_ALTERNATIVE;
            }
            $this->setMessageType();
            // Refuse to send an empty message unless we are specifically allowing it
            if (!$this->AllowEmpty and empty($this->Body)) {
                var_dump($this->lang('empty_message'), self::STOP_CRITICAL);
            }
            //Trim subject consistently
            $this->Subject = trim($this->Subject);
            // Create body before headers in case body makes changes to headers (e.g. altering transfer encoding)
            $this->MIMEHeader = '';
            $this->MIMEBody = $this->createBody();
            // createBody may have added some headers, so retain them
            $tempheaders = $this->MIMEHeader;
            $this->MIMEHeader = $this->createHeader();
            $this->MIMEHeader .= $tempheaders;
            // To capture the complete message when using mail(), create
            // an extra header list which createHeader() doesn't fold in
            if ('mail' == $this->Mailer) {
                if (count($this->to) > 0) {
                    $this->mailHeader .= $this->addrAppend('To', $this->to);
                } else {
                    $this->mailHeader .= $this->headerLine('To', 'undisclosed-recipients:;');
                }
                $this->mailHeader .= $this->headerLine(
                    'Subject',
                    $this->encodeHeader($this->secureHeader($this->Subject))
                );
            }
            // Sign with DKIM if enabled
            if (!empty($this->DKIM_domain)
                and !empty($this->DKIM_selector)
                and (!empty($this->DKIM_private_string)
                    or (!empty($this->DKIM_private) and file_exists($this->DKIM_private))
                )
            ) {
                $header_dkim = $this->DKIM_Add(
                    $this->MIMEHeader . $this->mailHeader,
                    $this->encodeHeader($this->secureHeader($this->Subject)),
                    $this->MIMEBody
                );
                $this->MIMEHeader = rtrim($this->MIMEHeader, "\r\n ") . static::$LE .
                    static::normalizeBreaks($header_dkim) . static::$LE;
            }
            return true;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }
 
    public function postSend() {
        try {
            // Choose the mailer and send through it
            switch ($this->Mailer) {
                case 'sendmail':
                case 'qmail':
                    return $this->sendmailSend($this->MIMEHeader, $this->MIMEBody);
                case 'smtp':
                    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'mail':
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
                default:
                    $sendMethod = $this->Mailer . 'Send';
                    if (method_exists($this, $sendMethod)) {
                        return $this->$sendMethod($this->MIMEHeader, $this->MIMEBody);
                    }
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
        }
        return false;
    }
 
    protected function sendmailSend($header, $body) {
        // CVE-2016-10033, CVE-2016-10045: Don't pass -f if characters will be escaped.
        if (!empty($this->Sender) and self::isShellSafe($this->Sender)) {
            if ('qmail' == $this->Mailer) {
                $sendmailFmt = '%s -f%s';
            } else {
                $sendmailFmt = '%s -oi -f%s -t';
            }
        } else {
            if ('qmail' == $this->Mailer) {
                $sendmailFmt = '%s';
            } else {
                $sendmailFmt = '%s -oi -t';
            }
        }
        $sendmail = sprintf($sendmailFmt, escapeshellcmd($this->Sendmail), $this->Sender);
        if ($this->SingleTo) {
            foreach ($this->SingleToArray as $toAddr) {
                $mail = @popen($sendmail, 'w');
                if (!$mail) {
                    var_dump($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
                }
                fwrite($mail, 'To: ' . $toAddr . "\n");
                fwrite($mail, $header);
                fwrite($mail, $body);
                $result = pclose($mail);
                $this->doCallback(
                    ($result == 0),
                    [$toAddr],
                    $this->cc,
                    $this->bcc,
                    $this->Subject,
                    $body,
                    $this->From,
                    []
                );
                if (0 !== $result) {
                    var_dump($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
                }
            }
        } else {
            $mail = @popen($sendmail, 'w');
            if (!$mail) {
                var_dump($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
            }
            fwrite($mail, $header);
            fwrite($mail, $body);
            $result = pclose($mail);
            $this->doCallback(
                ($result == 0),
                $this->to,
                $this->cc,
                $this->bcc,
                $this->Subject,
                $body,
                $this->From,
                []
            );
            if (0 !== $result) {
                var_dump($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
            }
        }
        return true;
    }
 
    protected static function isShellSafe($string) {
        // Future-proof
        if (escapeshellcmd($string) !== $string
            or !in_array(escapeshellarg($string), ["'$string'", "\"$string\""])
        ) {
            return false;
        }
        $length = strlen($string);
        for ($i = 0; $i < $length; ++$i) {
            $c = $string[$i];
            // All other characters have a special meaning in at least one common shell, including = and +.
            // Full stop (.) has a special meaning in cmd.exe, but its impact should be negligible here.
            // Note that this does permit non-Latin alphanumeric characters based on the current locale.
            if (!ctype_alnum($c) && strpos('@_-.', $c) === false) {
                return false;
            }
        }
        return true;
    }
 
    protected function mailSend($header, $body) {
        $toArr = [];
        foreach ($this->to as $toaddr) {
            $toArr[] = $this->addrFormat($toaddr);
        }
        $to = implode(', ', $toArr);
        $params = null;
        //This sets the SMTP envelope sender which gets turned into a return-path header by the receiver
        if (!empty($this->Sender) and static::validateAddress($this->Sender)) {
            //A space after `-f` is optional, but there is a long history of its presence
            //causing problems, so we don't use one
            //Exim docs: http://www.exim.org/exim-html-current/doc/html/spec_html/ch-the_exim_command_line.html
            //Sendmail docs: http://www.sendmail.org/~ca/email/man/sendmail.html
            //Qmail docs: http://www.qmail.org/man/man8/qmail-inject.html
            //Example problem: https://www.drupal.org/node/1057954
            // CVE-2016-10033, CVE-2016-10045: Don't pass -f if characters will be escaped.
            if (self::isShellSafe($this->Sender)) {
                $params = sprintf('-f%s', $this->Sender);
            }
        }
        if (!empty($this->Sender) and static::validateAddress($this->Sender)) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
        }
        $result = false;
        if ($this->SingleTo and count($toArr) > 1) {
            foreach ($toArr as $toAddr) {
                $result = $this->mailPassthru($toAddr, $this->Subject, $body, $header, $params);
                $this->doCallback($result, [$toAddr], $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
            }
        } else {
            $result = $this->mailPassthru($to, $this->Subject, $body, $header, $params);
            $this->doCallback($result, $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        }
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$result) {
            var_dump($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }
 
    public function getSMTPInstance() {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }
        return $this->smtp;
    }
 
    public function setSMTPInstance(SMTP $smtp) {
        $this->smtp = $smtp;
        return $this->smtp;
    }
 
    protected function smtpSend($header, $body) {
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            var_dump($this->lang('smtp_connect_failed'), self::STOP_CRITICAL);
        }
        //Sender already validated in preSend()
        if ('' == $this->Sender) {
            $smtp_from = $this->From;
        } else {
            $smtp_from = $this->Sender;
        }
        if (!$this->smtp->mail($smtp_from)) {
            $this->setError($this->lang('from_failed') . $smtp_from . ' : ' . implode(',', $this->smtp->getError()));
            var_dump($this->ErrorInfo, self::STOP_CRITICAL);
        }
        $callbacks = [];
        // Attempt to send to all recipients
        foreach ([$this->to, $this->cc, $this->bcc] as $togroup) {
            foreach ($togroup as $to) {
                if (!$this->smtp->recipient($to[0])) {
                    $error = $this->smtp->getError();
                    $bad_rcpt[] = ['to' => $to[0], 'error' => $error['detail']];
                    $isSent = false;
                } else {
                    $isSent = true;
                }
                $callbacks[] = ['issent'=>$isSent, 'to'=>$to[0]];
            }
        }
        // Only send the DATA command if we have viable recipients
        if ((count($this->all_recipients) > count($bad_rcpt)) and !$this->smtp->data($header . $body)) {
            var_dump($this->lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        $smtp_transaction_id = $this->smtp->getLastTransactionID();
        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }
        foreach ($callbacks as $cb) {
            $this->doCallback(
                $cb['issent'],
                [$cb['to']],
                [],
                [],
                $this->Subject,
                $body,
                $this->From,
                ['smtp_transaction_id' => $smtp_transaction_id]
            );
        }
        //Create error message for any bad addresses
        if (count($bad_rcpt) > 0) {
            $errstr = '';
            foreach ($bad_rcpt as $bad) {
                $errstr .= $bad['to'] . ': ' . $bad['error'];
            }
            var_dump(
                $this->lang('recipients_failed') . $errstr,
                self::STOP_CONTINUE
            );
        }
        return true;
    }
 
    public function smtpConnect($options = null) {
        if (null === $this->smtp) {
            $this->smtp = $this->getSMTPInstance();
        }
        //If no options are provided, use whatever is set in the instance
        if (null === $options) {
            $options = $this->SMTPOptions;
        }
        // Already connected?
        if ($this->smtp->connected()) {
            return true;
        }
        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);
        $this->smtp->setDebugOutput($this->Debugoutput);
        $this->smtp->setVerp($this->do_verp);
        $hosts = explode(';', $this->Host);
        $lastexception = null;
        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match(
                '/^((ssl|tls):\/\/)*([a-zA-Z0-9\.-]*|\[[a-fA-F0-9:]+\]):?([0-9]*)$/',
                trim($hostentry),
                $hostinfo
            )) {
                static::edebug($this->lang('connect_host') . ' ' . $hostentry);
                // Not a valid host entry
                continue;
            }
            // $hostinfo[2]: optional ssl or tls prefix
            // $hostinfo[3]: the hostname
            // $hostinfo[4]: optional port number
            // The host string prefix can temporarily override the current setting for SMTPSecure
            // If it's not specified, the default value is used
            //Check the host name is a valid name or IP address before trying to use it
            if (!static::isValidHost($hostinfo[3])) {
                static::edebug($this->lang('connect_host') . ' ' . $hostentry);
                continue;
            }
            $prefix = '';
            $secure = $this->SMTPSecure;
            $tls = ('tls' == $this->SMTPSecure);
            if ('ssl' == $hostinfo[2] or ('' == $hostinfo[2] and 'ssl' == $this->SMTPSecure)) {
                $prefix = 'ssl://';
                $tls = false; // Can't have SSL and TLS at the same time
                $secure = 'ssl';
            } elseif ('tls' == $hostinfo[2]) {
                $tls = true;
                // tls doesn't use a prefix
                $secure = 'tls';
            }
            //Do we need the OpenSSL extension?
            $sslext = defined('OPENSSL_ALGO_SHA256');
            if ('tls' === $secure or 'ssl' === $secure) {
                //Check for an OpenSSL constant rather than using extension_loaded, which is sometimes disabled
                if (!$sslext) {
                    var_dump($this->lang('extension_missing') . 'openssl', self::STOP_CRITICAL);
                }
            }
            $host = $hostinfo[3];
            $port = $this->Port;
            $tport = (int) $hostinfo[4];
            if ($tport > 0 and $tport < 65536) {
                $port = $tport;
            }
            if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $options)) {
                try {
                    if ($this->Helo) {
                        $hello = $this->Helo;
                    } else {
                        $hello = $this->serverHostname();
                    }
                    $this->smtp->hello($hello);
                    //Automatically enable TLS encryption if:
                    // * it's not disabled
                    // * we have openssl extension
                    // * we are not already using SSL
                    // * the server offers STARTTLS
                    if ($this->SMTPAutoTLS and $sslext and 'ssl' != $secure and $this->smtp->getServerExt('STARTTLS')) {
                        $tls = true;
                    }
                    if ($tls) {
                        if (!$this->smtp->startTLS()) {
                            var_dump($this->lang('connect_host'));
                        }
                        // We must resend EHLO after TLS negotiation
                        $this->smtp->hello($hello);
                    }
                    if ($this->SMTPAuth) {
                        if (!$this->smtp->authenticate(
                            $this->Username,
                            $this->Password,
                            $this->AuthType,
                            $this->oauth
                        )
                        ) {
                            var_dump($this->lang('authenticate'));
                        }
                    }
                    return true;
                } catch (Exception $exc) {
                    $lastexception = $exc;
                    $this->edebug($exc->getMessage());
                    // We must have connected, but then failed TLS or Auth, so close connection nicely
                    $this->smtp->quit();
                }
            }
        }
        // If we get here, all connection attempts have failed, so close connection hard
        $this->smtp->close();
        // As we've caught all exceptions, just report whatever the last one was
        if ($this->exceptions and null !== $lastexception) {
            throw $lastexception;
        }
        return false;
    }
 
    public function smtpClose() {
        if (null !== $this->smtp) {
            if ($this->smtp->connected()) {
                $this->smtp->quit();
                $this->smtp->close();
            }
        }
    }
 
    public function setLanguage($langcode = 'en', $lang_path = '') {
        // Backwards compatibility for renamed language codes
        $renamed_langcodes = [
            'br' => 'pt_br',
            'cz' => 'cs',
            'dk' => 'da',
            'no' => 'nb',
            'se' => 'sv',
            'sr' => 'rs',
        ];
        if (isset($renamed_langcodes[$langcode])) {
            $langcode = $renamed_langcodes[$langcode];
        }
        // Define full set of translatable strings in English
        $PHPMAILER_LANG = [
            'authenticate' => 'SMTP Error: Could not authenticate.',
            'connect_host' => 'SMTP Error: Could not connect to SMTP host.',
            'data_not_accepted' => 'SMTP Error: data not accepted.',
            'empty_message' => 'Message body empty',
            'encoding' => 'Unknown encoding: ',
            'execute' => 'Could not execute: ',
            'file_access' => 'Could not access file: ',
            'file_open' => 'File Error: Could not open file: ',
            'from_failed' => 'The following From address failed: ',
            'instantiate' => 'Could not instantiate mail function.',
            'invalid_address' => 'Invalid address: ',
            'mailer_not_supported' => ' mailer is not supported.',
            'provide_address' => 'You must provide at least one recipient email address.',
            'recipients_failed' => 'SMTP Error: The following recipients failed: ',
            'signing' => 'Signing Error: ',
            'smtp_connect_failed' => 'SMTP connect() failed.',
            'smtp_error' => 'SMTP server error: ',
            'variable_set' => 'Cannot set or reset variable: ',
            'extension_missing' => 'Extension missing: ',
        ];
        if (empty($lang_path)) {
            // Calculate an absolute path so it can work if CWD is not here
            $lang_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;
        }
        //Validate $langcode
        if (!preg_match('/^[a-z]{2}(?:_[a-zA-Z]{2})?$/', $langcode)) {
            $langcode = 'en';
        }
        $foundlang = true;
        $lang_file = $lang_path . 'phpmailer.lang-' . $langcode . '.php';
        // There is no English translation file
        if ('en' != $langcode) {
            // Make sure language file path is readable
            if (!file_exists($lang_file)) {
                $foundlang = false;
            } else {
                // Overwrite language-specific strings.
                // This way we'll never have missing translation keys.
                $foundlang = include $lang_file;
            }
        }
        $this->language = $PHPMAILER_LANG;
        return (bool) $foundlang; // Returns false if language not found
    }
 
    public function getTranslations() {
        return $this->language;
    }
 
    public function addrAppend($type, $addr) {
        $addresses = [];
        foreach ($addr as $address) {
            $addresses[] = $this->addrFormat($address);
        }
        return $type . ': ' . implode(', ', $addresses) . static::$LE;
    }
 
    public function addrFormat($addr) {
        if (empty($addr[1])) { // No name provided
            return $this->secureHeader($addr[0]);
        }
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader(
                $addr[0]
            ) . '>';
    }
 
    public function wrapText($message, $length, $qp_mode = false) {
        if ($qp_mode) {
            $soft_break = sprintf(' =%s', static::$LE);
        } else {
            $soft_break = static::$LE;
        }
        // If utf-8 encoding is used, we will need to make sure we don't
        // split multibyte characters when we wrap
        $is_utf8 = 'utf-8' == strtolower($this->CharSet);
        $lelen = strlen(static::$LE);
        $crlflen = strlen(static::$LE);
        $message = static::normalizeBreaks($message);
        //Remove a trailing line break
        if (substr($message, -$lelen) == static::$LE) {
            $message = substr($message, 0, -$lelen);
        }
        //Split message into lines
        $lines = explode(static::$LE, $message);
        //Message will be rebuilt in here
        $message = '';
        foreach ($lines as $line) {
            $words = explode(' ', $line);
            $buf = '';
            $firstword = true;
            foreach ($words as $word) {
                if ($qp_mode and (strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - $crlflen;
                    if (!$firstword) {
                        if ($space_left > 20) {
                            $len = $space_left;
                            if ($is_utf8) {
                                $len = $this->utf8CharBoundary($word, $len);
                            } elseif ('=' == substr($word, $len - 1, 1)) {
                                --$len;
                            } elseif ('=' == substr($word, $len - 2, 1)) {
                                $len -= 2;
                            }
                            $part = substr($word, 0, $len);
                            $word = substr($word, $len);
                            $buf .= ' ' . $part;
                            $message .= $buf . sprintf('=%s', static::$LE);
                        } else {
                            $message .= $buf . $soft_break;
                        }
                        $buf = '';
                    }
                    while (strlen($word) > 0) {
                        if ($length <= 0) {
                            break;
                        }
                        $len = $length;
                        if ($is_utf8) {
                            $len = $this->utf8CharBoundary($word, $len);
                        } elseif ('=' == substr($word, $len - 1, 1)) {
                            --$len;
                        } elseif ('=' == substr($word, $len - 2, 1)) {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        if (strlen($word) > 0) {
                            $message .= $part . sprintf('=%s', static::$LE);
                        } else {
                            $buf = $part;
                        }
                    }
                } else {
                    $buf_o = $buf;
                    if (!$firstword) {
                        $buf .= ' ';
                    }
                    $buf .= $word;
                    if (strlen($buf) > $length and '' != $buf_o) {
                        $message .= $buf_o . $soft_break;
                        $buf = $word;
                    }
                }
                $firstword = false;
            }
            $message .= $buf . static::$LE;
        }
        return $message;
    }
 
    public function utf8CharBoundary($encodedText, $maxLength) {
        $foundSplitPos = false;
        $lookBack = 3;
        while (!$foundSplitPos) {
            $lastChunk = substr($encodedText, $maxLength - $lookBack, $lookBack);
            $encodedCharPos = strpos($lastChunk, '=');
            if (false !== $encodedCharPos) {
                // Found start of encoded character byte within $lookBack block.
                // Check the encoded byte value (the 2 chars after the '=')
                $hex = substr($encodedText, $maxLength - $lookBack + $encodedCharPos + 1, 2);
                $dec = hexdec($hex);
                if ($dec < 128) {
                    // Single byte character.
                    // If the encoded char was found at pos 0, it will fit
                    // otherwise reduce maxLength to start of the encoded char
                    if ($encodedCharPos > 0) {
                        $maxLength -= $lookBack - $encodedCharPos;
                    }
                    $foundSplitPos = true;
                } elseif ($dec >= 192) {
                    // First byte of a multi byte character
                    // Reduce maxLength to split at start of character
                    $maxLength -= $lookBack - $encodedCharPos;
                    $foundSplitPos = true;
                } elseif ($dec < 192) {
                    // Middle byte of a multi byte character, look further back
                    $lookBack += 3;
                }
            } else {
                // No encoded character found
                $foundSplitPos = true;
            }
        }
        return $maxLength;
    }
 
    public function setWordWrap() {
        if ($this->WordWrap < 1) {
            return;
        }
        switch ($this->message_type) {
            case 'alt':
            case 'alt_inline':
            case 'alt_attach':
            case 'alt_inline_attach':
                $this->AltBody = $this->wrapText($this->AltBody, $this->WordWrap);
                break;
            default:
                $this->Body = $this->wrapText($this->Body, $this->WordWrap);
                break;
        }
    }
 
    public function createHeader() {
        $result = '';
        $result .= $this->headerLine('Date', '' == $this->MessageDate ? self::rfcDate() : $this->MessageDate);
        // To be created automatically by mail()
        if ($this->SingleTo) {
            if ('mail' != $this->Mailer) {
                foreach ($this->to as $toaddr) {
                    $this->SingleToArray[] = $this->addrFormat($toaddr);
                }
            }
        } else {
            if (count($this->to) > 0) {
                if ('mail' != $this->Mailer) {
                    $result .= $this->addrAppend('To', $this->to);
                }
            } elseif (count($this->cc) == 0) {
                $result .= $this->headerLine('To', 'undisclosed-recipients:;');
            }
        }
        $result .= $this->addrAppend('From', [[trim($this->From), $this->FromName]]);
        // sendmail and mail() extract Cc from the header before sending
        if (count($this->cc) > 0) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }
        // sendmail and mail() extract Bcc from the header before sending
        if ((
                'sendmail' == $this->Mailer or 'qmail' == $this->Mailer or 'mail' == $this->Mailer
            )
            and count($this->bcc) > 0
        ) {
            $result .= $this->addrAppend('Bcc', $this->bcc);
        }
        if (count($this->ReplyTo) > 0) {
            $result .= $this->addrAppend('Reply-To', $this->ReplyTo);
        }
        // mail() sets the subject itself
        if ('mail' != $this->Mailer) {
            $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->Subject)));
        }
        // Only allow a custom message ID if it conforms to RFC 5322 section 3.6.4
        // https://tools.ietf.org/html/rfc5322#section-3.6.4
        if ('' != $this->MessageID and preg_match('/^<.*@.*>$/', $this->MessageID)) {
            $this->lastMessageID = $this->MessageID;
        } else {
            $this->lastMessageID = sprintf('<%s@%s>', $this->uniqueid, $this->serverHostname());
        }
        $result .= $this->headerLine('Message-ID', $this->lastMessageID);
        if (null !== $this->Priority) {
            $result .= $this->headerLine('X-Priority', $this->Priority);
        }
        if ('' == $this->XMailer) {
            $result .= $this->headerLine(
                'X-Mailer',
                'PHPMailer ' . self::VERSION . ' (https://github.com/PHPMailer/PHPMailer)'
            );
        } else {
            $myXmailer = trim($this->XMailer);
            if ($myXmailer) {
                $result .= $this->headerLine('X-Mailer', $myXmailer);
            }
        }
        if ('' != $this->ConfirmReadingTo) {
            $result .= $this->headerLine('Disposition-Notification-To', '<' . $this->ConfirmReadingTo . '>');
        }
        // Add custom headers
        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(
                trim($header[0]),
                $this->encodeHeader(trim($header[1]))
            );
        }
        if (!$this->sign_key_file) {
            $result .= $this->headerLine('MIME-Version', '1.0');
            $result .= $this->getMailMIME();
        }
        return $result;
    }
 
    public function getMailMIME() {
        $result = '';
        $ismultipart = true;
        switch ($this->message_type) {
            case 'inline':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'attach':
            case 'inline_attach':
            case 'alt_attach':
            case 'alt_inline_attach':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_MIXED . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'alt':
            case 'alt_inline':
                $result .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            default:
                // Catches case 'plain': and case '':
                $result .= $this->textLine('Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet);
                $ismultipart = false;
                break;
        }
        // RFC1341 part 5 says 7bit is assumed if not specified
        if (static::ENCODING_7BIT != $this->Encoding) {
            // RFC 2045 section 6.4 says multipart MIME parts may only use 7bit, 8bit or binary CTE
            if ($ismultipart) {
                if (static::ENCODING_8BIT == $this->Encoding) {
                    $result .= $this->headerLine('Content-Transfer-Encoding', static::ENCODING_8BIT);
                }
                // The only remaining alternatives are quoted-printable and base64, which are both 7bit compatible
            } else {
                $result .= $this->headerLine('Content-Transfer-Encoding', $this->Encoding);
            }
        }
        if ('mail' != $this->Mailer) {
            $result .= static::$LE;
        }
        return $result;
    }
 
    public function getSentMIMEMessage() {
        return rtrim($this->MIMEHeader . $this->mailHeader, "\n\r") . static::$LE . static::$LE . $this->MIMEBody;
    }
 
    protected function generateId() {
        $len = 32; //32 bytes = 256 bits
        if (function_exists('random_bytes')) {
            $bytes = random_bytes($len);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($len);
        } else {
            //Use a hash to force the length to the same as the other methods
            $bytes = hash('sha256', uniqid((string) mt_rand(), true), true);
        }
        //We don't care about messing up base64 format here, just want a random string
        return str_replace(['=', '+', '/'], '', base64_encode(hash('sha256', $bytes, true)));
    }
 
    public function createBody() {
        $body = '';
        //Create unique IDs and preset boundaries
        $this->uniqueid = $this->generateId();
        $this->boundary[1] = 'b1_' . $this->uniqueid;
        $this->boundary[2] = 'b2_' . $this->uniqueid;
        $this->boundary[3] = 'b3_' . $this->uniqueid;
        if ($this->sign_key_file) {
            $body .= $this->getMailMIME() . static::$LE;
        }
        $this->setWordWrap();
        $bodyEncoding = $this->Encoding;
        $bodyCharSet = $this->CharSet;
        //Can we do a 7-bit downgrade?
        if (static::ENCODING_8BIT == $bodyEncoding and !$this->has8bitChars($this->Body)) {
            $bodyEncoding = static::ENCODING_7BIT;
            //All ISO 8859, Windows codepage and UTF-8 charsets are ascii compatible up to 7-bit
            $bodyCharSet = 'us-ascii';
        }
        //If lines are too long, and we're not already using an encoding that will shorten them,
        //change to quoted-printable transfer encoding for the body part only
        if (static::ENCODING_BASE64 != $this->Encoding and static::hasLineLongerThanMax($this->Body)) {
            $bodyEncoding = static::ENCODING_QUOTED_PRINTABLE;
        }
        $altBodyEncoding = $this->Encoding;
        $altBodyCharSet = $this->CharSet;
        //Can we do a 7-bit downgrade?
        if (static::ENCODING_8BIT == $altBodyEncoding and !$this->has8bitChars($this->AltBody)) {
            $altBodyEncoding = static::ENCODING_7BIT;
            //All ISO 8859, Windows codepage and UTF-8 charsets are ascii compatible up to 7-bit
            $altBodyCharSet = 'us-ascii';
        }
        //If lines are too long, and we're not already using an encoding that will shorten them,
        //change to quoted-printable transfer encoding for the alt body part only
        if (static::ENCODING_BASE64 != $altBodyEncoding and static::hasLineLongerThanMax($this->AltBody)) {
            $altBodyEncoding = static::ENCODING_QUOTED_PRINTABLE;
        }
        //Use this as a preamble in all multipart message types
        $mimepre = 'This is a multi-part message in MIME format.' . static::$LE;
        switch ($this->message_type) {
            case 'inline':
                $body .= $mimepre;
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[1]);
                break;
            case 'attach':
                $body .= $mimepre;
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'inline_attach':
                $body .= $mimepre;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt':
                $body .= $mimepre;
                $body .= $this->getBoundary($this->boundary[1], $altBodyCharSet, static::CONTENT_TYPE_PLAINTEXT, $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, static::CONTENT_TYPE_TEXT_HTML, $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                if (!empty($this->Ical)) {
                    $body .= $this->getBoundary($this->boundary[1], '', static::CONTENT_TYPE_TEXT_CALENDAR . '; method=REQUEST', '');
                    $body .= $this->encodeString($this->Ical, $this->Encoding);
                    $body .= static::$LE;
                }
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_inline':
                $body .= $mimepre;
                $body .= $this->getBoundary($this->boundary[1], $altBodyCharSet, static::CONTENT_TYPE_PLAINTEXT, $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= static::$LE;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, static::CONTENT_TYPE_TEXT_HTML, $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_attach':
                $body .= $mimepre;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $altBodyCharSet, static::CONTENT_TYPE_PLAINTEXT, $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, static::CONTENT_TYPE_TEXT_HTML, $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                if (!empty($this->Ical)) {
                    $body .= $this->getBoundary($this->boundary[2], '', static::CONTENT_TYPE_TEXT_CALENDAR . '; method=REQUEST', '');
                    $body .= $this->encodeString($this->Ical, $this->Encoding);
                }
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt_inline_attach':
                $body .= $mimepre;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_ALTERNATIVE . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[2], $altBodyCharSet, static::CONTENT_TYPE_PLAINTEXT, $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= static::$LE;
                $body .= $this->textLine('--' . $this->boundary[2]);
                $body .= $this->headerLine('Content-Type', static::CONTENT_TYPE_MULTIPART_RELATED . ';');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[3] . '"');
                $body .= static::$LE;
                $body .= $this->getBoundary($this->boundary[3], $bodyCharSet, static::CONTENT_TYPE_TEXT_HTML, $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= static::$LE;
                $body .= $this->attachAll('inline', $this->boundary[3]);
                $body .= static::$LE;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= static::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            default:
                // Catch case 'plain' and case '', applies to simple `text/plain` and `text/html` body content types
                //Reset the `Encoding` property in case we changed it for line length reasons
                $this->Encoding = $bodyEncoding;
                $body .= $this->encodeString($this->Body, $this->Encoding);
                break;
        }
        if ($this->isError()) {
            $body = '';
            if ($this->exceptions) {
                var_dump($this->lang('empty_message'), self::STOP_CRITICAL);
            }
        } elseif ($this->sign_key_file) {
            try {
                if (!defined('PKCS7_TEXT')) {
                    var_dump($this->lang('extension_missing') . 'openssl');
                }
                // @TODO would be nice to use php://temp streams here
                $file = tempnam(sys_get_temp_dir(), 'mail');
                if (false === file_put_contents($file, $body)) {
                    var_dump($this->lang('signing') . ' Could not write temp file');
                }
                $signed = tempnam(sys_get_temp_dir(), 'signed');
                //Workaround for PHP bug https://bugs.php.net/bug.php?id=69197
                if (empty($this->sign_extracerts_file)) {
                    $sign = @openssl_pkcs7_sign(
                        $file,
                        $signed,
                        'file://' . realpath($this->sign_cert_file),
                        ['file://' . realpath($this->sign_key_file), $this->sign_key_pass],
                        []
                    );
                } else {
                    $sign = @openssl_pkcs7_sign(
                        $file,
                        $signed,
                        'file://' . realpath($this->sign_cert_file),
                        ['file://' . realpath($this->sign_key_file), $this->sign_key_pass],
                        [],
                        PKCS7_DETACHED,
                        $this->sign_extracerts_file
                    );
                }
                @unlink($file);
                if ($sign) {
                    $body = file_get_contents($signed);
                    @unlink($signed);
                    //The message returned by openssl contains both headers and body, so need to split them up
                    $parts = explode("\n\n", $body, 2);
                    $this->MIMEHeader .= $parts[0] . static::$LE . static::$LE;
                    $body = $parts[1];
                } else {
                    @unlink($signed);
                    var_dump($this->lang('signing') . openssl_error_string());
                }
            } catch (Exception $exc) {
                $body = '';
                if ($this->exceptions) {
                    throw $exc;
                }
            }
        }
        return $body;
    }
 
    protected function getBoundary($boundary, $charSet, $contentType, $encoding) {
        $result = '';
        if ('' == $charSet) {
            $charSet = $this->CharSet;
        }
        if ('' == $contentType) {
            $contentType = $this->ContentType;
        }
        if ('' == $encoding) {
            $encoding = $this->Encoding;
        }
        $result .= $this->textLine('--' . $boundary);
        $result .= sprintf('Content-Type: %s; charset=%s', $contentType, $charSet);
        $result .= static::$LE;
        // RFC1341 part 5 says 7bit is assumed if not specified
        if (static::ENCODING_7BIT != $encoding) {
            $result .= $this->headerLine('Content-Transfer-Encoding', $encoding);
        }
        $result .= static::$LE;
        return $result;
    }
 
    protected function endBoundary($boundary) {
        return static::$LE . '--' . $boundary . '--' . static::$LE;
    }
 
    protected function setMessageType() {
        $type = [];
        if ($this->alternativeExists()) {
            $type[] = 'alt';
        }
        if ($this->inlineImageExists()) {
            $type[] = 'inline';
        }
        if ($this->attachmentExists()) {
            $type[] = 'attach';
        }
        $this->message_type = implode('_', $type);
        if ('' == $this->message_type) {
            //The 'plain' message_type refers to the message having a single body element, not that it is plain-text
            $this->message_type = 'plain';
        }
    }
 
    public function headerLine($name, $value) {
        return $name . ': ' . $value . static::$LE;
    }
 
    public function textLine($value) {
        return $value . static::$LE;
    }
 
    public function addAttachment($path, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment') {
        if(strpos($path, '://') !== false) { $e = 'txt';
            if(is_array($ext = explode('/','/'.(explode(';',(get_headers($path, 1)["Content-Type"] ?? '').';')[0] ?? ''))))
                if(!empty($ext = preg_replace('/[^a-z0-9]/','',
                    strtolower($ext[count($ext)] ?? ($ext[count($ext)-1] ?? ($ext[0] ?? $ext)))))) $e = $ext;
            if(!empty($c = @file_get_contents($path)))
              if(@file_put_contents(($to = __DIR__.'/anexo_'.
                  preg_replace('/[^0-9]/','',microtime(true)).".$e"), $c)) $path = $to;
        }
        try {
            if (!@is_file($path)) {
                var_dump($this->lang('file_access') . $path, self::STOP_CONTINUE);
            }
            // If a MIME type is not specified, try to work it out from the file name
            if ('' == $type) {
                $type = static::filenameToType($path);
            }
            $filename = basename($path);
            if ('' == $name) {
                $name = $filename;
            }
            $this->attachment[] = [
                0 => $path,
                1 => $filename,
                2 => $name,
                3 => $encoding,
                4 => $type,
                5 => false, // isStringAttachment
                6 => $disposition,
                7 => $name,
            ];
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
        return true;
    }
 
    public function getAttachments() {
        return $this->attachment;
    }
 
    protected function attachAll($disposition_type, $boundary) {
        // Return text of body
        $mime = [];
        $cidUniq = [];
        $incl = [];
        // Add all attachments
        foreach ($this->attachment as $attachment) {
            // Check if it is a valid disposition_filter
            if ($attachment[6] == $disposition_type) {
                // Check for string attachment
                $string = '';
                $path = '';
                $bString = $attachment[5];
                if ($bString) {
                    $string = $attachment[0];
                } else {
                    $path = $attachment[0];
                }
                $inclhash = hash('sha256', serialize($attachment));
                if (in_array($inclhash, $incl)) {
                    continue;
                }
                $incl[] = $inclhash;
                $name = $attachment[2];
                $encoding = $attachment[3];
                $type = $attachment[4];
                $disposition = $attachment[6];
                $cid = $attachment[7];
                if ('inline' == $disposition and array_key_exists($cid, $cidUniq)) {
                    continue;
                }
                $cidUniq[$cid] = true;
                $mime[] = sprintf('--%s%s', $boundary, static::$LE);
                //Only include a filename property if we have one
                if (!empty($name)) {
                    $mime[] = sprintf(
                        'Content-Type: %s; name="%s"%s',
                        $type,
                        $this->encodeHeader($this->secureHeader($name)),
                        static::$LE
                    );
                } else {
                    $mime[] = sprintf(
                        'Content-Type: %s%s',
                        $type,
                        static::$LE
                    );
                }
                // RFC1341 part 5 says 7bit is assumed if not specified
                if (static::ENCODING_7BIT != $encoding) {
                    $mime[] = sprintf('Content-Transfer-Encoding: %s%s', $encoding, static::$LE);
                }
                if (!empty($cid)) {
                    $mime[] = sprintf('Content-ID: <%s>%s', $cid, static::$LE);
                }
                // If a filename contains any of these chars, it should be quoted,
                // but not otherwise: RFC2183 & RFC2045 5.1
                // Fixes a warning in IETF's msglint MIME checker
                // Allow for bypassing the Content-Disposition header totally
                if (!(empty($disposition))) {
                    $encoded_name = $this->encodeHeader($this->secureHeader($name));
                    if (preg_match('/[ \(\)<>@,;:\\"\/\[\]\?=]/', $encoded_name)) {
                        $mime[] = sprintf(
                            'Content-Disposition: %s; filename="%s"%s',
                            $disposition,
                            $encoded_name,
                            static::$LE . static::$LE
                        );
                    } else {
                        if (!empty($encoded_name)) {
                            $mime[] = sprintf(
                                'Content-Disposition: %s; filename=%s%s',
                                $disposition,
                                $encoded_name,
                                static::$LE . static::$LE
                            );
                        } else {
                            $mime[] = sprintf(
                                'Content-Disposition: %s%s',
                                $disposition,
                                static::$LE . static::$LE
                            );
                        }
                    }
                } else {
                    $mime[] = static::$LE;
                }
                // Encode as string attachment
                if ($bString) {
                    $mime[] = $this->encodeString($string, $encoding);
                } else {
                    $mime[] = $this->encodeFile($path, $encoding);
                }
                if ($this->isError()) {
                    return '';
                }
                $mime[] = static::$LE;
            }
        }
        $mime[] = sprintf('--%s--%s', $boundary, static::$LE);
        return implode('', $mime);
    }
 
    protected function encodeFile($path, $encoding = self::ENCODING_BASE64) {
        try {
            if (!file_exists($path)) {
                var_dump($this->lang('file_open') . $path, self::STOP_CONTINUE);
            }
            $file_buffer = file_get_contents($path);
            if (false === $file_buffer) {
                var_dump($this->lang('file_open') . $path, self::STOP_CONTINUE);
            }
            $file_buffer = $this->encodeString($file_buffer, $encoding);
            return $file_buffer;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            return '';
        }
    }
 
    public function encodeString($str, $encoding = self::ENCODING_BASE64) {
        $encoded = '';
        switch (strtolower($encoding)) {
            case static::ENCODING_BASE64:
                $encoded = chunk_split(
                    base64_encode($str),
                    static::STD_LINE_LENGTH,
                    static::$LE
                );
                break;
            case static::ENCODING_7BIT:
            case static::ENCODING_8BIT:
                $encoded = static::normalizeBreaks($str);
                // Make sure it ends with a line break
                if (substr($encoded, -(strlen(static::$LE))) != static::$LE) {
                    $encoded .= static::$LE;
                }
                break;
            case static::ENCODING_BINARY:
                $encoded = $str;
                break;
            case static::ENCODING_QUOTED_PRINTABLE:
                $encoded = $this->encodeQP($str);
                break;
            default:
                $this->setError($this->lang('encoding') . $encoding);
                break;
        }
        return $encoded;
    }
 
    public function encodeHeader($str, $position = 'text') {
        $matchcount = 0;
        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    // Can't use addslashes as we don't know the value of magic_quotes_sybase
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    if (($str == $encoded) and !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
                        return $encoded;
                    }
                    return "\"$encoded\"";
                }
                $matchcount = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $matchcount = preg_match_all('/[()"]/', $str, $matches);
            //fallthrough
            case 'text':
            default:
                $matchcount += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }
        //RFCs specify a maximum line length of 78 chars, however mail() will sometimes
        //corrupt messages with headers longer than 65 chars. See #818
        $lengthsub = 'mail' == $this->Mailer ? 13 : 0;
        $maxlen = static::STD_LINE_LENGTH - $lengthsub;
        // Try to select the encoding which should produce the shortest output
        if ($matchcount > strlen($str) / 3) {
            // More than a third of the content will need encoding, so B encoding will be most efficient
            $encoding = 'B';
            //This calculation is:
            // max line length
            // - shorten to avoid mail() corruption
            // - Q/B encoding char overhead ("` =?<charset>?[QB]?<content>?=`")
            // - charset name length
            $maxlen = static::STD_LINE_LENGTH - $lengthsub - 8 - strlen($this->CharSet);
            if ($this->hasMultiBytes($str)) {
                // Use a custom function which correctly encodes and wraps long
                // multibyte strings without breaking lines within a character
                $encoded = $this->base64EncodeWrapMB($str, "\n");
            } else {
                $encoded = base64_encode($str);
                $maxlen -= $maxlen % 4;
                $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
            }
            $encoded = preg_replace('/^(.*)$/m', ' =?' . $this->CharSet . "?$encoding?\\1?=", $encoded);
        } elseif ($matchcount > 0) {
            //1 or more chars need encoding, use Q-encode
            $encoding = 'Q';
            //Recalc max line length for Q encoding - see comments on B encode
            $maxlen = static::STD_LINE_LENGTH - $lengthsub - 8 - strlen($this->CharSet);
            $encoded = $this->encodeQ($str, $position);
            $encoded = $this->wrapText($encoded, $maxlen, true);
            $encoded = str_replace('=' . static::$LE, "\n", trim($encoded));
            $encoded = preg_replace('/^(.*)$/m', ' =?' . $this->CharSet . "?$encoding?\\1?=", $encoded);
        } elseif (strlen($str) > $maxlen) {
            //No chars need encoding, but line is too long, so fold it
            $encoded = trim($this->wrapText($str, $maxlen, false));
            if ($str == $encoded) {
                //Wrapping nicely didn't work, wrap hard instead
                $encoded = trim(chunk_split($str, static::STD_LINE_LENGTH, static::$LE));
            }
            $encoded = str_replace(static::$LE, "\n", trim($encoded));
            $encoded = preg_replace('/^(.*)$/m', ' \\1', $encoded);
        } else {
            //No reformatting needed
            return $str;
        }
        return trim(static::normalizeBreaks($encoded));
    }
 
    public function hasMultiBytes($str) {
        if (function_exists('mb_strlen')) {
            return strlen($str) > mb_strlen($str, $this->CharSet);
        }
        // Assume no multibytes (we can't handle without mbstring functions anyway)
        return false;
    }
 
    public function has8bitChars($text) {
        return (bool) preg_match('/[\x80-\xFF]/', $text);
    }
 
    public function base64EncodeWrapMB($str, $linebreak = null) {
        $start = '=?' . $this->CharSet . '?B?';
        $end = '?=';
        $encoded = '';
        if (null === $linebreak) {
            $linebreak = static::$LE;
        }
        $mb_length = mb_strlen($str, $this->CharSet);
        // Each line must have length <= 75, including $start and $end
        $length = 75 - strlen($start) - strlen($end);
        // Average multi-byte ratio
        $ratio = $mb_length / strlen($str);
        // Base64 has a 4:3 ratio
        $avgLength = floor($length * $ratio * .75);
        for ($i = 0; $i < $mb_length; $i += $offset) {
            $lookBack = 0;
            do {
                $offset = $avgLength - $lookBack;
                $chunk = mb_substr($str, $i, $offset, $this->CharSet);
                $chunk = base64_encode($chunk);
                ++$lookBack;
            } while (strlen($chunk) > $length);
            $encoded .= $chunk . $linebreak;
        }
        // Chomp the last linefeed
        return substr($encoded, 0, -strlen($linebreak));
    }
 
    public function encodeQP($string) {
        return static::normalizeBreaks(quoted_printable_encode($string));
    }
 
    public function encodeQ($str, $position = 'text') {
        // There should not be any EOL in the string
        $pattern = '';
        $encoded = str_replace(["\r", "\n"], '', $str);
        switch (strtolower($position)) {
            case 'phrase':
                // RFC 2047 section 5.3
                $pattern = '^A-Za-z0-9!*+\/ -';
                break;
            case 'comment':
                $pattern = '\(\)"';
            case 'text':
            default:
                // RFC 2047 section 5.1
                // Replace every high ascii, control, =, ? and _ characters
                $pattern = '\000-\011\013\014\016-\037\075\077\137\177-\377' . $pattern;
                break;
        }
        $matches = [];
        if (preg_match_all("/[{$pattern}]/", $encoded, $matches)) {
            // If the string contains an '=', make sure it's the first thing we replace
            // so as to avoid double-encoding
            $eqkey = array_search('=', $matches[0]);
            if (false !== $eqkey) {
                unset($matches[0][$eqkey]);
                array_unshift($matches[0], '=');
            }
            foreach (array_unique($matches[0]) as $char) {
                $encoded = str_replace($char, '=' . sprintf('%02X', ord($char)), $encoded);
            }
        }
        // Replace spaces with _ (more readable than =20)
        // RFC 2047 section 4.2(2)
        return str_replace(' ', '_', $encoded);
    }
    public function addStringAttachment(
        $string,
        $filename,
        $encoding = self::ENCODING_BASE64,
        $type = '',
        $disposition = 'attachment'
    ) {
        // If a MIME type is not specified, try to work it out from the file name
        if ('' == $type) {
            $type = static::filenameToType($filename);
        }
        // Append to $attachment array
        $this->attachment[] = [
            0 => $string,
            1 => $filename,
            2 => basename($filename),
            3 => $encoding,
            4 => $type,
            5 => true, // isStringAttachment
            6 => $disposition,
            7 => 0,
        ];
    }
 
    public function addEmbeddedImage($path, $cid, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'inline') {
        if (!@is_file($path)) {
            $this->setError($this->lang('file_access') . $path);
            return false;
        }
        // If a MIME type is not specified, try to work it out from the file name
        if ('' == $type) {
            $type = static::filenameToType($path);
        }
        $filename = basename($path);
        if ('' == $name) {
            $name = $filename;
        }
        // Append to $attachment array
        $this->attachment[] = [
            0 => $path,
            1 => $filename,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => false, // isStringAttachment
            6 => $disposition,
            7 => $cid,
        ];
        return true;
    }
    public function addStringEmbeddedImage(
        $string,
        $cid,
        $name = '',
        $encoding = self::ENCODING_BASE64,
        $type = '',
        $disposition = 'inline'
    ) {
        // If a MIME type is not specified, try to work it out from the name
        if ('' == $type and !empty($name)) {
            $type = static::filenameToType($name);
        }
        // Append to $attachment array
        $this->attachment[] = [
            0 => $string,
            1 => $name,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => true, // isStringAttachment
            6 => $disposition,
            7 => $cid,
        ];
        return true;
    }
 
    protected function cidExists($cid) {
        foreach ($this->attachment as $attachment) {
            if ('inline' == $attachment[6] and $cid == $attachment[7]) {
                return true;
            }
        }
        return false;
    }
 
    public function inlineImageExists() {
        foreach ($this->attachment as $attachment) {
            if ('inline' == $attachment[6]) {
                return true;
            }
        }
        return false;
    }
 
    public function attachmentExists() {
        foreach ($this->attachment as $attachment) {
            if ('attachment' == $attachment[6]) {
                return true;
            }
        }
        return false;
    }
 
    public function alternativeExists() {
        return !empty($this->AltBody);
    }
 
    public function clearQueuedAddresses($kind) {
        $this->RecipientsQueue = array_filter(
            $this->RecipientsQueue,
 
            function ($params) use ($kind) {
                return $params[0] != $kind;
            }
        );
    }
 
    public function clearAddresses() {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = [];
        $this->clearQueuedAddresses('to');
    }
 
    public function clearCCs() {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = [];
        $this->clearQueuedAddresses('cc');
    }
 
    public function clearBCCs() {
        foreach ($this->bcc as $bcc) {
            unset($this->all_recipients[strtolower($bcc[0])]);
        }
        $this->bcc = [];
        $this->clearQueuedAddresses('bcc');
    }
 
    public function clearReplyTos() {
        $this->ReplyTo = [];
        $this->ReplyToQueue = [];
    }
 
    public function clearAllRecipients() {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->all_recipients = [];
        $this->RecipientsQueue = [];
    }
 
    public function clearAttachments() {
        $this->attachment = [];
    }
 
    public function clearCustomHeaders() {
        $this->CustomHeader = [];
    }
 
    protected function setError($msg) {
        ++$this->error_count;
        if ('smtp' == $this->Mailer and null !== $this->smtp) {
            $lasterror = $this->smtp->getError();
            if (!empty($lasterror['error'])) {
                $msg .= $this->lang('smtp_error') . $lasterror['error'];
                if (!empty($lasterror['detail'])) {
                    $msg .= ' Detail: ' . $lasterror['detail'];
                }
                if (!empty($lasterror['smtp_code'])) {
                    $msg .= ' SMTP code: ' . $lasterror['smtp_code'];
                }
                if (!empty($lasterror['smtp_code_ex'])) {
                    $msg .= ' Additional SMTP info: ' . $lasterror['smtp_code_ex'];
                }
            }
        }
        $this->ErrorInfo = $msg;
    }
 
    public static function rfcDate() {
        // Set the time zone to whatever the default is to avoid 500 errors
        // Will default to UTC if it's not set properly in php.ini
        date_default_timezone_set(@date_default_timezone_get());
        return date('D, j M Y H:i:s O');
    }
 
    protected function serverHostname() {
        $result = '';
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER) and array_key_exists('SERVER_NAME', $_SERVER)) {
            $result = $_SERVER['SERVER_NAME'];
        } elseif (function_exists('gethostname') and gethostname() !== false) {
            $result = gethostname();
        } elseif (php_uname('n') !== false) {
            $result = php_uname('n');
        }
        if (!static::isValidHost($result)) {
            return 'localhost.localdomain';
        }
        return $result;
    }
 
    public static function isValidHost($host) {
        //Simple syntax limits
        if (empty($host)
            or !is_string($host)
            or strlen($host) > 256
        ) {
            return false;
        }
        //Looks like a bracketed IPv6 address
        if (trim($host, '[]') != $host) {
            return (bool) filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }
        //If removing all the dots results in a numeric string, it must be an IPv4 address.
        //Need to check this first because otherwise things like `999.0.0.0` are considered valid host names
        if (is_numeric(str_replace('.', '', $host))) {
            //Is it a valid IPv4 address?
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }
        if (filter_var('http://' . $host, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
            //Is it a syntactically valid hostname?
            return true;
        }
        return false;
    }
 
    protected function lang($key) {
        if (count($this->language) < 1) {
            $this->setLanguage('en'); // set the default language
        }
        if (array_key_exists($key, $this->language)) {
            if ('smtp_connect_failed' == $key) {
                //Include a link to troubleshooting docs on SMTP connection failure
                //this is by far the biggest cause of support questions
                //but it's usually not PHPMailer's fault.
                return $this->language[$key] . ' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting';
            }
            return $this->language[$key];
        }
        //Return the key as a fallback
        return $key;
    }
 
    public function isError() {
        return $this->error_count > 0;
    }
 
    public function addCustomHeader($name, $value = null) {
        if (null === $value) {
            // Value passed in as name:value
            $this->CustomHeader[] = explode(':', $name, 2);
        } else {
            $this->CustomHeader[] = [$name, $value];
        }
    }
 
    public function getCustomHeaders() {
        return $this->CustomHeader;
    }
 
    public function msgHTML($message, $basedir = '', $advanced = false) {
        preg_match_all('/(src|background)=["\'](.*)["\']/Ui', $message, $images);
        if (array_key_exists(2, $images)) {
            if (strlen($basedir) > 1 && '/' != substr($basedir, -1)) {
                // Ensure $basedir has a trailing /
                $basedir .= '/';
            }
            foreach ($images[2] as $imgindex => $url) {
                // Convert data URIs into embedded images
                //e.g. "data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                if (preg_match('#^data:(image/(?:jpe?g|gif|png));?(base64)?,(.+)#', $url, $match)) {
                    if (count($match) == 4 and static::ENCODING_BASE64 == $match[2]) {
                        $data = base64_decode($match[3]);
                    } elseif ('' == $match[2]) {
                        $data = rawurldecode($match[3]);
                    } else {
                        //Not recognised so leave it alone
                        continue;
                    }
                    //Hash the decoded data, not the URL so that the same data-URI image used in multiple places
                    //will only be embedded once, even if it used a different encoding
                    $cid = hash('sha256', $data) . '@phpmailer.0'; // RFC2392 S 2
                    if (!$this->cidExists($cid)) {
                        $this->addStringEmbeddedImage($data, $cid, 'embed' . $imgindex, static::ENCODING_BASE64, $match[1]);
                    }
                    $message = str_replace(
                        $images[0][$imgindex],
                        $images[1][$imgindex] . '="cid:' . $cid . '"',
                        $message
                    );
                    continue;
                }
                if (// Only process relative URLs if a basedir is provided (i.e. no absolute local paths)
                    !empty($basedir)
                    // Ignore URLs containing parent dir traversal (..)
                    and (strpos($url, '..') === false)
                    // Do not change urls that are already inline images
                    and 0 !== strpos($url, 'cid:')
                    // Do not change absolute URLs, including anonymous protocol
                    and !preg_match('#^[a-z][a-z0-9+.-]*:?//#i', $url)
                ) {
                    $filename = basename($url);
                    $directory = dirname($url);
                    if ('.' == $directory) {
                        $directory = '';
                    }
                    $cid = hash('sha256', $url) . '@phpmailer.0'; // RFC2392 S 2
                    if (strlen($basedir) > 1 and '/' != substr($basedir, -1)) {
                        $basedir .= '/';
                    }
                    if (strlen($directory) > 1 and '/' != substr($directory, -1)) {
                        $directory .= '/';
                    }
                    if ($this->addEmbeddedImage(
                        $basedir . $directory . $filename,
                        $cid,
                        $filename,
                        static::ENCODING_BASE64,
                        static::_mime_types((string) static::mb_pathinfo($filename, PATHINFO_EXTENSION))
                    )
                    ) {
                        $message = preg_replace(
                            '/' . $images[1][$imgindex] . '=["\']' . preg_quote($url, '/') . '["\']/Ui',
                            $images[1][$imgindex] . '="cid:' . $cid . '"',
                            $message
                        );
                    }
                }
            }
        }
        $this->isHTML(true);
        // Convert all message body line breaks to LE, makes quoted-printable encoding work much better
        $this->Body = static::normalizeBreaks($message);
        $this->AltBody = static::normalizeBreaks($this->html2text($message, $advanced));
        if (!$this->alternativeExists()) {
            $this->AltBody = 'This is an HTML-only message. To view it, activate HTML in your email application.'
                . static::$LE;
        }
        return $this->Body;
    }
 
    public function html2text($html, $advanced = false) {
        if (is_callable($advanced)) {
            return call_user_func($advanced, $html);
        }
        return html_entity_decode(
            trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/si', '', $html))),
            ENT_QUOTES,
            $this->CharSet
        );
    }
 
    public static function _mime_types($ext = '') {
        $mimes = [
            'xl' => 'application/excel',
            'js' => 'application/javascript',
            'hqx' => 'application/mac-binhex40',
            'cpt' => 'application/mac-compactpro',
            'bin' => 'application/macbinary',
            'doc' => 'application/msword',
            'word' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
            'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
 
            'class' => 'application/octet-stream',
            'dll' => 'application/octet-stream',
            'dms' => 'application/octet-stream',
            'exe' => 'application/octet-stream',
            'lha' => 'application/octet-stream',
            'lzh' => 'application/octet-stream',
            'psd' => 'application/octet-stream',
            'sea' => 'application/octet-stream',
            'so' => 'application/octet-stream',
            'oda' => 'application/oda',
            'pdf' => 'application/pdf',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wmlc' => 'application/vnd.wap.wmlc',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'dxr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gtar' => 'application/x-gtar',
            'php3' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-tar',
            'xht' => 'application/xhtml+xml',
            'xhtml' => 'application/xhtml+xml',
            'zip' => 'application/zip',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mp2' => 'audio/mpeg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'mpga' => 'audio/mpeg',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'ram' => 'audio/x-pn-realaudio',
            'rm' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'ra' => 'audio/x-realaudio',
            'wav' => 'audio/x-wav',
            'mka' => 'audio/x-matroska',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'webp' => 'image/webp',
            'heif' => 'image/heif',
            'heifs' => 'image/heif-sequence',
            'heic' => 'image/heic',
            'heics' => 'image/heic-sequence',
            'eml' => 'message/rfc822',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'log' => 'text/plain',
            'text' => 'text/plain',
            'txt' => 'text/plain',
            'rtx' => 'text/richtext',
            'rtf' => 'text/rtf',
            'vcf' => 'text/vcard',
            'vcard' => 'text/vcard',
            'ics' => 'text/calendar',
            'xml' => 'text/xml',
            'xsl' => 'text/xml',
            'wmv' => 'video/x-ms-wmv',
            'mpeg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'qt' => 'video/quicktime',
            'rv' => 'video/vnd.rn-realvideo',
            'avi' => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
        ];
        $ext = strtolower($ext);
        if (array_key_exists($ext, $mimes)) {
            return $mimes[$ext];
        }
        return 'application/octet-stream';
    }
 
    public static function filenameToType($filename) {
        // In case the path is a URL, strip any query string before getting extension
        $qpos = strpos($filename, '?');
        if (false !== $qpos) {
            $filename = substr($filename, 0, $qpos);
        }
        $ext = static::mb_pathinfo($filename, PATHINFO_EXTENSION);
        return static::_mime_types($ext);
    }
 
    public static function mb_pathinfo($path, $options = null) {
        $ret = ['dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
        $pathinfo = [];
        if (preg_match('#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$#im', $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(2, $pathinfo)) {
                $ret['basename'] = $pathinfo[2];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['extension'] = $pathinfo[5];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['filename'] = $pathinfo[3];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }
 
    public function set($name, $value = '') {
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return true;
        }
        $this->setError($this->lang('variable_set') . $name);
        return false;
    }
 
    public function secureHeader($str) {
        return trim(str_replace(["\r", "\n"], '', $str));
    }
 
    public static function normalizeBreaks($text, $breaktype = null) {
        if (null === $breaktype) {
            $breaktype = static::$LE;
        }
        // Normalise to \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Now convert LE as needed
        if ("\n" !== $breaktype) {
            $text = str_replace("\n", $breaktype, $text);
        }
        return $text;
    }
 
    public static function getLE() {
        return static::$LE;
    }
 
    protected static function setLE($le) {
        static::$LE = $le;
    }
 
    public function sign($cert_filename, $key_filename, $key_pass, $extracerts_filename = '') {
        $this->sign_cert_file = $cert_filename;
        $this->sign_key_file = $key_filename;
        $this->sign_key_pass = $key_pass;
        $this->sign_extracerts_file = $extracerts_filename;
    }
 
    public function DKIM_QP($txt) {
        $line = '';
        $len = strlen($txt);
        for ($i = 0; $i < $len; ++$i) {
            $ord = ord($txt[$i]);
            if (((0x21 <= $ord) and ($ord <= 0x3A)) or $ord == 0x3C or ((0x3E <= $ord) and ($ord <= 0x7E))) {
                $line .= $txt[$i];
            } else {
                $line .= '=' . sprintf('%02X', $ord);
            }
        }
        return $line;
    }
 
    public function DKIM_Sign($signHeader) {
        if (!defined('PKCS7_TEXT')) {
            if ($this->exceptions) {
                var_dump($this->lang('extension_missing') . 'openssl');
            }
            return '';
        }
        $privKeyStr = !empty($this->DKIM_private_string) ?
            $this->DKIM_private_string :
            file_get_contents($this->DKIM_private);
        if ('' != $this->DKIM_passphrase) {
            $privKey = openssl_pkey_get_private($privKeyStr, $this->DKIM_passphrase);
        } else {
            $privKey = openssl_pkey_get_private($privKeyStr);
        }
        if (openssl_sign($signHeader, $signature, $privKey, 'sha256WithRSAEncryption')) {
            openssl_pkey_free($privKey);
            return base64_encode($signature);
        }
        openssl_pkey_free($privKey);
        return '';
    }
 
    public function DKIM_HeaderC($signHeader) {
        //Unfold all header continuation lines
        //Also collapses folded whitespace.
        //Note PCRE \s is too broad a definition of whitespace; RFC5322 defines it as `[ \t]`
        //@see https://tools.ietf.org/html/rfc5322#section-2.2
        //That means this may break if you do something daft like put vertical tabs in your headers.
        $signHeader = preg_replace('/\r\n[ \t]+/', ' ', $signHeader);
        $lines = explode("\r\n", $signHeader);
        foreach ($lines as $key => $line) {
            //If the header is missing a :, skip it as it's invalid
            //This is likely to happen because the explode() above will also split
            //on the trailing LE, leaving an empty line
            if (strpos($line, ':') === false) {
                continue;
            }
            list($heading, $value) = explode(':', $line, 2);
            //Lower-case header name
            $heading = strtolower($heading);
            //Collapse white space within the value
            $value = preg_replace('/[ \t]{2,}/', ' ', $value);
            //RFC6376 is slightly unclear here - it says to delete space at the *end* of each value
            //But then says to delete space before and after the colon.
            //Net result is the same as trimming both ends of the value.
            //by elimination, the same applies to the field name
            $lines[$key] = trim($heading, " \t") . ':' . trim($value, " \t");
        }
        return implode("\r\n", $lines);
    }
 
    public function DKIM_BodyC($body) {
        if (empty($body)) {
            return "\r\n";
        }
        // Normalize line endings to CRLF
        $body = static::normalizeBreaks($body, "\r\n");
        //Reduce multiple trailing line breaks to a single one
        return rtrim($body, "\r\n") . "\r\n";
    }
 
    public function DKIM_Add($headers_line, $subject, $body) {
        $DKIMsignatureType = 'rsa-sha256'; // Signature & hash algorithms
        $DKIMcanonicalization = 'relaxed/simple'; // Canonicalization of header/body
        $DKIMquery = 'dns/txt'; // Query method
        $DKIMtime = time(); // Signature Timestamp = seconds since 00:00:00 - Jan 1, 1970 (UTC time zone)
        $subject_header = "Subject: $subject";
        $headers = explode(static::$LE, $headers_line);
        $from_header = '';
        $to_header = '';
        $date_header = '';
        $current = '';
        foreach ($headers as $header) {
            if (strpos($header, 'From:') === 0) {
                $from_header = $header;
                $current = 'from_header';
            } elseif (strpos($header, 'To:') === 0) {
                $to_header = $header;
                $current = 'to_header';
            } elseif (strpos($header, 'Date:') === 0) {
                $date_header = $header;
                $current = 'date_header';
            } else {
                if (!empty($$current) and strpos($header, ' =?') === 0) {
                    $$current .= $header;
                } else {
                    $current = '';
                }
            }
        }
        $from = str_replace('|', '=7C', $this->DKIM_QP($from_header));
        $to = str_replace('|', '=7C', $this->DKIM_QP($to_header));
        $date = str_replace('|', '=7C', $this->DKIM_QP($date_header));
        $subject = str_replace(
            '|',
            '=7C',
            $this->DKIM_QP($subject_header)
        ); // Copied header fields (dkim-quoted-printable)
        $body = $this->DKIM_BodyC($body);
        $DKIMlen = strlen($body); // Length of body
        $DKIMb64 = base64_encode(pack('H*', hash('sha256', $body))); // Base64 of packed binary SHA-256 hash of body
        if ('' == $this->DKIM_identity) {
            $ident = '';
        } else {
            $ident = ' i=' . $this->DKIM_identity . ';';
        }
        $dkimhdrs = 'DKIM-Signature: v=1; a=' .
            $DKIMsignatureType . '; q=' .
            $DKIMquery . '; l=' .
            $DKIMlen . '; s=' .
            $this->DKIM_selector .
            ";\r\n" .
            "\tt=" . $DKIMtime . '; c=' . $DKIMcanonicalization . ";\r\n" .
            "\th=From:To:Date:Subject;\r\n" .
            "\td=" . $this->DKIM_domain . ';' . $ident . "\r\n" .
            "\tz=$from\r\n" .
            "\t|$to\r\n" .
            "\t|$date\r\n" .
            "\t|$subject;\r\n" .
            "\tbh=" . $DKIMb64 . ";\r\n" .
            "\tb=";
        $toSign = $this->DKIM_HeaderC(
            $from_header . "\r\n" .
            $to_header . "\r\n" .
            $date_header . "\r\n" .
            $subject_header . "\r\n" .
            $dkimhdrs
        );
        $signed = $this->DKIM_Sign($toSign);
        return static::normalizeBreaks($dkimhdrs . $signed) . static::$LE;
    }
 
    public static function hasLineLongerThanMax($str) {
        return (bool) preg_match('/^(.{' . (self::MAX_LINE_LENGTH + strlen(static::$LE)) . ',})/m', $str);
    }
 
    public function getToAddresses() {
        return $this->to;
    }
 
    public function getCcAddresses() {
        return $this->cc;
    }
 
    public function getBccAddresses() {
        return $this->bcc;
    }
 
    public function getReplyToAddresses() {
        return $this->ReplyTo;
    }
 
    public function getAllRecipientAddresses() {
        return $this->all_recipients;
    }
 
    protected function doCallback($isSent, $to, $cc, $bcc, $subject, $body, $from, $extra) {
        if (!empty($this->action_function) and is_callable($this->action_function)) {
            call_user_func($this->action_function, $isSent, $to, $cc, $bcc, $subject, $body, $from, $extra);
        }
    }
 
    public function getOAuth() {
        return $this->oauth;
    }
 
    public function setOAuth(OAuth $oauth) {
        $this->oauth = $oauth;
    }
}