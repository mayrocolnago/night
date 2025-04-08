<?php
class storage {
    use \openapi;

    public static $uploadlocked = true; //you can unlock this with auto loaded modules that verifies user authentication

    /* databases that are automatically created to serve file storage */
    protected static function database() {
        pdo_query("CREATE TABLE IF NOT EXISTS `vault_hashes` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `hashcheck` longtext NOT NULL,
            `content` longblob DEFAULT NULL,
            `lastcheck` bigint(20) NOT NULL,
            `registered` bigint(20) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `hashcheck` (`hashcheck`(767)) USING BTREE)");
        
        pdo_query("CREATE TABLE IF NOT EXISTS `vault_files` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `filepath` longtext NOT NULL,
            `hashcheck` longtext NOT NULL,
            `tags` longtext DEFAULT NULL,
            `lastseen` bigint(20) NOT NULL,
            `lastchange` bigint(20) NOT NULL,
            `registered` bigint(20) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `hashcheck` (`hashcheck`(767)) USING BTREE,
            KEY `filepath` (`filepath`(767)) USING BTREE)");
    }

    /* returns the module script access URL. useful for filling in which URL we use for uploads */
    public static function url($data=[]) {
        return THISURL.'/storage/';
    }

    /* simple function to check if the upload module is functional */
    public static function ping($data=[]) {
        return [ 'result'=>strtotime('now'), 'host'=>self::url() ];
    }

    /* function that receives file uploads from the web */
    public static function send($data=[]) {
        //verify whether the upload is locked
        if(self::$uploadlocked ?? true) ['result'=>'', 'err'=>'error. access denied'];

        $source = (isset($_REQUEST['f'])) ? preg_replace('/[^0-9a-zA-Z\-\_]/','',$_REQUEST['f']) : 'non';
        $path = (isset($_REQUEST['p'])) ? preg_replace('/[^a-z\/\_]/','',$_REQUEST['p']) : '';
        $nome = ($source . '_' . ((isset($_REQUEST['n'])) ? preg_replace('/[^0-9a-zA-Z\-\_]/','',$_REQUEST['n']) : uniqid(strtotime('now'))));

        try { if(strlen($path) > 0) {
            if($path[strlen($path)-1] != '/') $path[strlen($path)] = '/';
            if($path[0] == '/') $path = substr($path,1,strlen($path)); }
        } catch(Exception $e) { $path = ''; }

        while (strpos($path,'__') !== false) { $path = str_replace('__','_',$path); }
        while (strpos($path,'//') !== false) { $path = str_replace('//','/',$path); }

        if(strlen(trim($path)) <= 4) $path = 'root/';

        self::database();

        //method via url download: ?download=1&file=http://remote/stream
        if((isset($_REQUEST['download'])) && (isset($_REQUEST['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_REQUEST['f'] ?? $_REQUEST['file'])['dot']));
            if(!($fp_remote = @fopen($_REQUEST['file'], 'rb'))) return ['result'=>'', 'err'=>'error reading remote file'];
            if(!($fp_local = @fopen($df = "/tmp/".$nome, 'wb'))) return ['result'=>'', 'err'=>'error writing local file'];
            while($buffer = @fread($fp_remote, 8192)) @fwrite($fp_local, $buffer);
            @fclose($fp_remote); @fclose($fp_local);
            if(!file_exists($df)) return ['result'=>'', 'err'=>'error downloading. probably /tmp/ permission issue'];
            else return self::storefile($novonome, @file_get_contents($df));   
        }

        //method via external url copy: ?fromurl=1&file=http://remote/image.png
        if((isset($_REQUEST['fromurl'])) && (isset($_REQUEST['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_REQUEST['f'] ?? $_REQUEST['file'])['dot']));
            if(!(@copy($_REQUEST['file'], ($df = "/tmp/".$nome)))) return ['result'=>'', 'err'=>'error. copy not completed'];
            else return self::storefile($novonome, @file_get_contents($df));
        }
        
        //method via base64: ?base64=1&file=base64:file;ABCdefGHT123==
        if((isset($_REQUEST['base64'])) && (isset($_REQUEST['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_REQUEST['f'] ?? $_REQUEST['file'])['dot']));
            list($tipo, $dados) = explode(';', $_REQUEST['file']);
            list(, $tipo) = explode(':', $tipo);
            list(, $dados) = explode(',', $dados);
            $arquivo_tmp = base64_decode($dados);
            return self::storefile($novonome, $arquivo_tmp);   
        }

        //method via direct write: ?getfile=1&file=content
        if((isset($_REQUEST['getfile'])) && (isset($_REQUEST['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_REQUEST['f'] ?? $_REQUEST['file'])['dot']));
            return self::storefile($novonome, $_REQUEST['file']);
        }
                
        //method via redirect only
        if((isset($_REQUEST['redirect'])) && (isset($_REQUEST['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_REQUEST['f'] ?? $_REQUEST['file'])['dot']));
            return self::storefile($novonome, $_REQUEST['file'], ['redirect'=>$_REQUEST['file']]);
        }

        //method via form upload
        if((isset($_FILES['file'])) && (!empty(@$_FILES['file']))) {
            $novonome = ($path . ($nome .= '_'.self::extension($_FILES['file']['name'] ?? '')['dot']));
            if(!file_exists($df = ($_FILES['file']['tmp_name'] ?? './void'))) return ['result'=>'', 'err'=>'error. file not found on tmp_dir '.$df];
            return self::storefile($novonome, @file_get_contents($df));
        }
                    
        return ['result'=>'', 'err'=>'error. no file found on parameters'];
    }

    /* function that saves the file to storage. it's public and can be used by other methods. returns the filename in the database */
    public static function storefile($filename, $content='', $tags=[]) {
        if(empty($content)) return '';
        $checksum = hash('sha256', $content); $strtotime = strtotime('now');
        
        if(pdo_query("UPDATE vault_files SET lastchange=:lc WHERE filepath=:fp 
                ORDER BY id DESC LIMIT 1",['lc'=>$strtotime, 'fp'=>"/$filename"]) < 1)
           pdo_insert("vault_files",[
               'filepath' => "/$filename",
               'hashcheck' => $checksum,
               'lastseen' => '0',
               'lastchange' => '0',
               'registered' => $strtotime,
               'tags' => $tags ]);
        
        if(!empty($tags['redirect'] ?? '')) return $filename;
        else
          if(pdo_query("UPDATE vault_hashes SET lastcheck=:lc WHERE hashcheck=:hc order by id desc limit 1",[
                'lc' => $strtotime, 'hc' => $checksum ]) > 0 && self::updatefiletags($checksum))
            return $filename;
          else
            if(pdo_insert("vault_hashes",[
                'hashcheck' => $checksum,
                'content' => $content,
                'lastcheck' => '0',
                'registered' => $strtotime ]) > 0 && self::updatefiletags($checksum))
              return $filename;
        return '';
    }

    /* mechanism for assigning tags to files. useful for later verification with /storage/folder/filename?details */
    private static function updatefiletags($hash,$params=[]) {
        if(empty(trim($hash))) return true;
        $ignoreparams = ['f', 'p', 'e', 'file', 'class', 'function', 'uid', 'token', 'actk', 'download', 'fromurl', 'base64', 'getfile'];
        foreach($_REQUEST as $k => $v) if(!in_array($k, $ignoreparams) && $v != 'function();') $params[$k] = $v;
        foreach($_GET as $k => $v) if(!in_array($k, $ignoreparams)) $params[$k] = $v;
        pdo_query("UPDATE vault_files SET tags=:tg WHERE hashcheck=:hc order by id desc limit 1",[ 'tg'=>$params, 'hc'=>$hash ]);
        return true;
    }

    /* shortcut function to load js module */
    public static function js() { self::upload(['_js' => true]); }

    /* javascript binding code to turn the element into an uploader */
    public static function upload($data=[]) {
        if(isset($data['_php'])) return self::send($data);
        if(!isset($data['_js'])) return -400;
        @header('Access-Control-Allow-Origin: *');
        @header('Content-Type: text/javascript');
        @header("Cache-Control: max-age=604800");
        ?><script>
            var upfilequeue = [];
            var upfilecountid = 0;
            var upfilesendtimer = null;
            var upfilescriptfuncarray = [];
            var upfilescriptuploading = false;
            var upstoragedefaultpathdir = '<?=self::url();?>';
            var upstoragedefault = upstoragedefaultpathdir;

            if(window.location.protocol === "https:") upstoragedefaultpathdir = String(upstoragedefaultpathdir).replace('http:','https:');

            function bindupload(element,params,onstart,ondone) {
                var elemparent = fupgetelemparent(element);
                if(elemparent != null) $(elemparent).attr('enctype','multipart/form-data'); else return;
                try { $(element).attr('name','file'); } catch(e) { console.log(e); }
                try { if(!params.f) params.f = 'upload'; if(!params.p) params.p = 'root/'; } catch(e) { params = {'f':'upload','p':'root/'}; }
                try { $(element).off('change'); } catch(e) { console.log('error unbinding upload',e); }
                try { $(element).on('change',function (event) { console.log('file upload triggered');
                    for(var i = 0; i < event.target.files.length; i++) {
                        var type = event.target.files[i].type; 
                        var name = event.target.files[i].name;
                        var size = event.target.files[i].size;
                        var elemparent = fupgetelemparent(this);
                        var retorno = { 'id':++upfilecountid, 'element':$(element).attr('id'), 'filename':name, 'filetype':type, 'filesize':size, 'uptime':((new Date()).getTime() / 1000) };
                        var filedata = null;
                        try { if(!params.mime) params.mime = type; } catch(e) { }
                        try { if(!params.size) params.size = size; } catch(e) { }
                        try { if(!params.filename) params.filename = name; } catch(e) { }
                        try { if(String($(elemparent).prop('tagName')).toLowerCase() == 'form') {
                            var previouselementid = $(element).attr('id');
                            var previousparentid = $(elemparent).attr('id');
                            $(elemparent).attr('id','taupformelem'+upfilecountid);
                            Object.keys(params).forEach(function (item) { 
                                var valor = params[item];
                                try { if(typeof valor == 'function') {
                                var funcnome = 'taupformelem'+upfilecountid+'name'+item;
                                upfilescriptfuncarray[funcnome] = valor;
                                valor = 'function();';
                                } } catch(err) { }
                                if(!($('#taupformelem'+upfilecountid+' input[name='+item+']').length)) $(elemparent).append('<input type="hidden" name="'+item+'" value="">');
                                $('#taupformelem'+upfilecountid+' input[name='+item+']').val(valor); });
                            filedata = (new FormData( document.getElementById('taupformelem'+upfilecountid) ));
                            $(elemparent).attr('id',previousparentid);
                            } else console.log('could not find form block');
                        } catch(e) { console.log('could not queue file ',e); } 
                        
                        if(filedata != null) retorno.filedata = filedata; else retorno = { 'id':0 };
                            
                        var resultfromstart = true;
                        try { resultfromstart = onstart(retorno); } catch(e) { console.log('could not callback onstart upload script',e); }
                        if((resultfromstart !== false) && (retorno.id)) { retorno.filedata = filedata; retorno.params = params; retorno.ondone = ondone; upfilequeue.push(retorno); }
                        }
                    });
                    console.log('upload element binding complete');
                } catch(e) { console.log('could not bind file upload trigger',e); }
            }

            function fupgetelemparent(elemobj) {
                var elempdetect = elemobj; var esearchform = true; var result = null;
                var upelemid = 0; var upelemtag = ''; var currentformtag = '';
                while (esearchform) {
                    upelemid++; if(upelemid > 9999) esearchform = false;
                    try { upelemtag = String($(elempdetect).prop('tagName')).toLowerCase(); } catch(e){ upelemid++; }
                    if((upelemtag == 'form') || (upelemtag == 'body') || (!esearchform)) break;
                    try { elempdetect = $(elempdetect).parent(); } catch(e){ upelemid++; } }
                try { result = (String($(elempdetect).prop('tagName')).toLowerCase() == 'form') ? elempdetect : null; }
                catch(e) { console.log('could not bind. missing form element block',elemobj); }
                return result;
            }

            function fileuploadintervalfunc() {
                if(upfilescriptuploading) return;
                if(!upfilequeue[0]) return;
                
                upfilescriptuploading = true;
                var getp = "?";
                var atual = upfilequeue[0];
                var retorno = new Object();
                try {
                    Object.keys(atual).forEach(function (item) {
                    if(!((item == 'ondone') || (item == 'params') || (item == 'filedata'))) retorno[item] = atual[item];
                    });
                    Object.keys(atual.params).forEach(function (item) {
                    if(typeof atual.params[item] !== 'function') return;
                    getp += '&'+item+'='+encodeURI(String(atual.params[item]()));
                    });
                } catch(err) { }
                retorno.sendtime = ((new Date()).getTime() / 1000);
                try {
                    $.ajax({ type: 'POST', url: upstoragedefaultpathdir+'send'+getp, data: atual.filedata,
                        cache: false, processData: false, contentType: false,
                        success: function (updata) {
                            retorno.elapsedtime = ((new Date()).getTime() / 1000) - retorno.sendtime;
                            retorno.result = ((updata.result) ? updata.result : '');
                            retorno.server = upstoragedefaultpathdir;
                            retorno.url = upstoragedefaultpathdir+((updata.result) ? updata.result : '');
                            retorno.err = ((updata.err) ? updata.err : '');
                            try { atual.ondone(retorno); } catch(e) { console.log('could not callback ondone upload script',e); }
                            upfilescriptuploading = false; upfilequeue.shift();
                        }, error: function(e) { upfilescriptuploading = false; } });
                } catch(e) { upfilescriptuploading = false; }
            }

            upfilesendtimer = setInterval(function(){
                try { fileuploadintervalfunc(); } catch(e) { }

                if($('img.storagedetails').length)
                    $('img.storagedetails').each(function(index,item){
                        $(item).removeClass('storagedetails').addClass('storagedetailloading');
                        $.post($(item).attr('src')+'?details',null,function(data){ try {
                        if(!data || !data.result || data.error) return;
                        if(String(data.data).trim().replace('undefined','').replace('null','').replace('[]','').replace('{}','').replace('NaN','') == '') return;
                        $(item).removeClass('storagedetailloading').addClass('storagedetailloaded');
                        var s = $(item).innerWidth();
                        var n = $(`<div class="upsldtscenvolc" style="position:relative;color:#FFF;text-shadow:2px 2px #000;font-size:${(s / 38)}px;overflow:hidden;"></div>`).insertBefore(item);
                        var e = $(item).detach(); $(n).append(e); var d = data.data;
                        var c = "";
                        var m = "";
                        if(d.endereco) c += `${d.endereco}<br>`;
                        if(d.latitude && d.longitude) { 
                            $(n).append(`<div onclick="window.open('https://maps.google.com/maps?q=${d.latitude},${d.longitude}&z=15','_new');"
                                        style="position:absolute;bottom:0px;left:0px;width:${(s / 3)}px;height:${(s / 4)}px;opacity:0.4;text-overflow:center;overflow:hidden;">
                                        <div style="position:absolute;top:-4rem;left:-2rem;right:-2rem;bottom:-5rem;pointer-events:none;">
                                        <iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0" style="width:100%;height:100%;" 
                                        src="https://maps.google.com/maps?q=${d.latitude},${d.longitude}&z=15&output=embed"></iframe></div></div>`);
                            c += `${d.latitude},${d.longitude}<br>`; }
                        Object.keys(d).forEach(function(key){
                            if(String(d[key]).trim().replace('undefined','').replace('null','').replace('[]','').replace('{}','').replace('NaN','') == '') return;
                            if(String(key).replace(/[^a-z]/gi,'') == '') return;
                            if(key == 'longitude') return;
                            if(key == 'latitude') return;
                            if(key == 'endereco') return;
                            m += `${key}: ${d[key]}<br>`; });
                        if(m !== "") c += `<details style="text-transform:capitalize;">${m}</details>`;
                        $(n).append(`<div class="detailstorageinformation encolor" style="position:absolute;right:0px;bottom:0px;text-align:right;max-width:50%;">${c}</div>`);
                        } catch(err) { console.log('Error loading photo metadata',err); } });
                    });
            },2000);
        </script><?php
    }

    /* function that returns the file contents to a variable */
    public static function get_contents($nameurl='',$details=false) {
        if(!is_string($nameurl)) return '';
        if(empty($filename = trim(str_replace('/storage/','',parse_url($nameurl, PHP_URL_PATH)),'/ '))) return '';
        if(!(is_array($data = [basename($filename)=>1, 'details'=>1]) && !empty($path = (dirname($filename).'/')))) return '';
        if(empty($hash = (($file = self::$path($data))['hash'] ?? ''))) return '';
        if($details) return $file;
        return ("data://application/octet-stream;base64,".base64_encode(
            pdo_fetch_item("SELECT content c FROM vault_hashes WHERE hashcheck=:hc ORDER BY id DESC LIMIT 1",[
                'hc' => $hash ])['c'] ?? ''));
    }

    /* function that calculates the file extension and returns both the extension and mimetype */
    public static function extension($data='') {
        if(!is_string($name = str_replace('.','_',($data['name'] ?? ($data ?? ''))))) $name = '_bin';
        if(!empty($e=($_REQUEST['e'] ?? '')) && is_string($e)) $name = '_'.substr(preg_replace('/[^a-zA-Z0-9\_]/','',$e),0,9);
        return [
            'dot' => ($extension = ((empty($fileext = substr(strrchr('_'.$name, '_'), 1))) ? "bin" : $fileext)),
            'mime' => (self::$mimes[$extension] ?? 'application/octet-stream')
        ];
    }

    /* method to call media through the URL (e.g. .../storage/folder/filename) */
    public static function __callStatic($name='',$arg=[]) {
        if(empty($filename = str_replace('.','_',"/$name".(array_keys($arg[0] ?? [])[0] ?? '')))) exit(' ');
        if(!is_string($filename)) exit(' ');
 
        $hash = (($file = pdo_fetch_item("SELECT id,hashcheck,tags 
                FROM vault_files WHERE filepath=:fp 
                AND json_value(tags,'$.protected') is null 
                ORDER BY id DESC LIMIT 1",['fp'=>$filename]))['hashcheck'] ?? '');

        $mime = ($file['tags']['mime'] ?? self::extension($filename)['mime']);

        if(isset($arg[0]['details']))
            return [
                'result'=>count($file), 
                'hash'=>$hash,
                'mime'=>$mime,
                'file'=>$filename,
                'data'=>($file['tags'] ?? []),
                'header'=>@header('Content-Type: application/json'),
                'policy'=>@header('Access-Control-Allow-Origin: *') ];
                
        @ini_set('zlib.output_compression', 'Off');
        @http_response_code(200);
        @header('Access-Control-Allow-Origin: *');
        if(!empty($mime)) @header("Content-Type: $mime");

        if(!empty($file['id'] ?? 0))
            if(pdo_query("UPDATE vault_files SET lastseen=:ls WHERE id=:vd LIMIT 1",['ls'=>strtotime('now'), 'vd'=>$file['id']]) || true)
                if(!empty($file['tags']['redirect'] ?? '')) exit(@header('Location: '.$file['tags']['redirect']));
                else if(!empty($id = ($vault = pdo_fetch_item("SELECT id,content FROM vault_hashes WHERE hashcheck=:hs ORDER BY id DESC LIMIT 1",['hs'=>$hash]))['id'] ?? '')) {
                        @header('Content-Length: ' . strlen($content = ($vault['content'] ?? ' ')));
                        exit($content); }
        
        exit(' ');
    }

    /* content type library to put in the document header when displaying. necessary to not always treat the file as a download */
    public static $mimes = [
        "txt" => "text/plain",
        "bin" => "application/octet-stream",
        "ez" => "application/andrew-inset",
        "aw" => "application/applixware",
        "atom" => "application/atom+xml",
        "atomcat" => "application/atomcat+xml",
        "atomsvc" => "application/atomsvc+xml",
        "ccxml" => "application/ccxml+xml",
        "cdmia" => "application/cdmi-capability",
        "cdmic" => "application/cdmi-container",
        "cdmid" => "application/cdmi-domain",
        "cdmio" => "application/cdmi-object",
        "cdmiq" => "application/cdmi-queue",
        "cu" => "application/cu-seeme",
        "davmount" => "application/davmount+xml",
        "dbk" => "application/docbook+xml",
        "dssc" => "application/dssc+der",
        "xdssc" => "application/dssc+xml",
        "ecma" => "application/ecmascript",
        "emma" => "application/emma+xml",
        "epub" => "application/epub+zip",
        "exi" => "application/exi",
        "pfr" => "application/font-tdpfr",
        "gml" => "application/gml+xml",
        "gpx" => "application/gpx+xml",
        "gxf" => "application/gxf",
        "stk" => "application/hyperstudio",
        "inkml" => "application/inkml+xml",
        "ipfix" => "application/ipfix",
        "jar" => "application/java-archive",
        "ser" => "application/java-serialized-object",
        "class" => "application/java-vm",
        "js" => "application/javascript",
        "json" => "application/json",
        "jsonml" => "application/jsonml+json",
        "lostxml" => "application/lost+xml",
        "hqx" => "application/mac-binhex40",
        "cpt" => "application/mac-compactpro",
        "mads" => "application/mads+xml",
        "mrc" => "application/marc",
        "mrcx" => "application/marcxml+xml",
        "mb" => "application/mathematica",
        "mathml" => "application/mathml+xml",
        "mbox" => "application/mbox",
        "mscml" => "application/mediaservercontrol+xml",
        "metalink" => "application/metalink+xml",
        "meta4" => "application/metalink4+xml",
        "mets" => "application/mets+xml",
        "mods" => "application/mods+xml",
        "mp21" => "application/mp21",
        "mp4s" => "application/mp4",
        "dot" => "application/msword",
        "mxf" => "application/mxf",
        "deploy" => "application/octet-stream",
        "oda" => "application/oda",
        "opf" => "application/oebps-package+xml",
        "ogx" => "application/ogg",
        "omdoc" => "application/omdoc+xml",
        "onepkg" => "application/onenote",
        "oxps" => "application/oxps",
        "xer" => "application/patch-ops-error+xml",
        "pdf" => "application/pdf",
        "pgp" => "application/pgp-encrypted",
        "sig" => "application/pgp-signature",
        "prf" => "application/pics-rules",
        "p10" => "application/pkcs10",
        "p7c" => "application/pkcs7-mime",
        "p7s" => "application/pkcs7-signature",
        "p8" => "application/pkcs8",
        "ac" => "application/pkix-attr-cert",
        "cer" => "application/pkix-cert",
        "crl" => "application/pkix-crl",
        "pkipath" => "application/pkix-pkipath",
        "pki" => "application/pkixcmp",
        "pls" => "application/pls+xml",
        "ps" => "application/postscript",
        "cww" => "application/prs.cww",
        "pskcxml" => "application/pskc+xml",
        "rdf" => "application/rdf+xml",
        "rif" => "application/reginfo+xml",
        "rnc" => "application/relax-ng-compact-syntax",
        "rl" => "application/resource-lists+xml",
        "rld" => "application/resource-lists-diff+xml",
        "rs" => "application/rls-services+xml",
        "gbr" => "application/rpki-ghostbusters",
        "mft" => "application/rpki-manifest",
        "roa" => "application/rpki-roa",
        "rsd" => "application/rsd+xml",
        "rss" => "application/rss+xml",
        "rtf" => "application/rtf",
        "sbml" => "application/sbml+xml",
        "scq" => "application/scvp-cv-request",
        "scs" => "application/scvp-cv-response",
        "spq" => "application/scvp-vp-request",
        "spp" => "application/scvp-vp-response",
        "sdp" => "application/sdp",
        "setpay" => "application/set-payment-initiation",
        "setreg" => "application/set-registration-initiation",
        "shf" => "application/shf+xml",
        "smil" => "application/smil+xml",
        "rq" => "application/sparql-query",
        "srx" => "application/sparql-results+xml",
        "gram" => "application/srgs",
        "grxml" => "application/srgs+xml",
        "sru" => "application/sru+xml",
        "ssdl" => "application/ssdl+xml",
        "ssml" => "application/ssml+xml",
        "teicorpus" => "application/tei+xml",
        "tfi" => "application/thraud+xml",
        "tsd" => "application/timestamped-data",
        "plb" => "application/vnd.3gpp.pic-bw-large",
        "psb" => "application/vnd.3gpp.pic-bw-small",
        "pvb" => "application/vnd.3gpp.pic-bw-var",
        "tcap" => "application/vnd.3gpp2.tcap",
        "pwn" => "application/vnd.3m.post-it-notes",
        "aso" => "application/vnd.accpac.simply.aso",
        "imp" => "application/vnd.accpac.simply.imp",
        "acu" => "application/vnd.acucobol",
        "acutc" => "application/vnd.acucorp",
        "air" => "application/vnd.adobe.air-application-installer-package+zip",
        "fcdt" => "application/vnd.adobe.formscentral.fcdt",
        "fxpl" => "application/vnd.adobe.fxp",
        "xdp" => "application/vnd.adobe.xdp+xml",
        "xfdf" => "application/vnd.adobe.xfdf",
        "ahead" => "application/vnd.ahead.space",
        "azf" => "application/vnd.airzip.filesecure.azf",
        "azs" => "application/vnd.airzip.filesecure.azs",
        "azw" => "application/vnd.amazon.ebook",
        "acc" => "application/vnd.americandynamics.acc",
        "ami" => "application/vnd.amiga.ami",
        "apk" => "application/vnd.android.package-archive",
        "cii" => "application/vnd.anser-web-certificate-issue-initiation",
        "fti" => "application/vnd.anser-web-funds-transfer-initiation",
        "atx" => "application/vnd.antix.game-component",
        "mpkg" => "application/vnd.apple.installer+xml",
        "m3u8" => "application/vnd.apple.mpegurl",
        "swi" => "application/vnd.aristanetworks.swi",
        "iota" => "application/vnd.astraea-software.iota",
        "aep" => "application/vnd.audiograph",
        "mpm" => "application/vnd.blueice.multipass",
        "bmi" => "application/vnd.bmi",
        "rep" => "application/vnd.businessobjects",
        "cdxml" => "application/vnd.chemdraw+xml",
        "mmd" => "application/vnd.chipnuts.karaoke-mmd",
        "cdy" => "application/vnd.cinderella",
        "cla" => "application/vnd.claymore",
        "rp9" => "application/vnd.cloanto.rp9",
        "c4u" => "application/vnd.clonk.c4group",
        "c11amc" => "application/vnd.cluetrust.cartomobile-config",
        "c11amz" => "application/vnd.cluetrust.cartomobile-config-pkg",
        "csp" => "application/vnd.commonspace",
        "cdbcmsg" => "application/vnd.contact.cmsg",
        "cmc" => "application/vnd.cosmocaller",
        "clkx" => "application/vnd.crick.clicker",
        "clkk" => "application/vnd.crick.clicker.keyboard",
        "clkp" => "application/vnd.crick.clicker.palette",
        "clkt" => "application/vnd.crick.clicker.template",
        "clkw" => "application/vnd.crick.clicker.wordbank",
        "wbs" => "application/vnd.criticaltools.wbs+xml",
        "pml" => "application/vnd.ctc-posml",
        "ppd" => "application/vnd.cups-ppd",
        "car" => "application/vnd.curl.car",
        "pcurl" => "application/vnd.curl.pcurl",
        "dart" => "application/vnd.dart",
        "rdz" => "application/vnd.data-vision.rdz",
        "uvvd" => "application/vnd.dece.data",
        "uvvt" => "application/vnd.dece.ttml+xml",
        "uvvx" => "application/vnd.dece.unspecified",
        "uvvz" => "application/vnd.dece.zip",
        "fe_launch" => "application/vnd.denovo.fcselayout-link",
        "dna" => "application/vnd.dna",
        "mlp" => "application/vnd.dolby.mlp",
        "dpg" => "application/vnd.dpgraph",
        "dfac" => "application/vnd.dreamfactory",
        "kpxx" => "application/vnd.ds-keypoint",
        "ait" => "application/vnd.dvb.ait",
        "svc" => "application/vnd.dvb.service",
        "geo" => "application/vnd.dynageo",
        "mag" => "application/vnd.ecowin.chart",
        "nml" => "application/vnd.enliven",
        "esf" => "application/vnd.epson.esf",
        "msf" => "application/vnd.epson.msf",
        "qam" => "application/vnd.epson.quickanime",
        "slt" => "application/vnd.epson.salt",
        "ssf" => "application/vnd.epson.ssf",
        "et3" => "application/vnd.eszigno3+xml",
        "ez2" => "application/vnd.ezpix-album",
        "ez3" => "application/vnd.ezpix-package",
        "fdf" => "application/vnd.fdf",
        "mseed" => "application/vnd.fdsn.mseed",
        "dataless" => "application/vnd.fdsn.seed",
        "gph" => "application/vnd.flographit",
        "ftc" => "application/vnd.fluxtime.clip",
        "book" => "application/vnd.framemaker",
        "fnc" => "application/vnd.frogans.fnc",
        "ltf" => "application/vnd.frogans.ltf",
        "fsc" => "application/vnd.fsc.weblaunch",
        "oas" => "application/vnd.fujitsu.oasys",
        "oa2" => "application/vnd.fujitsu.oasys2",
        "oa3" => "application/vnd.fujitsu.oasys3",
        "fg5" => "application/vnd.fujitsu.oasysgp",
        "bh2" => "application/vnd.fujitsu.oasysprs",
        "ddd" => "application/vnd.fujixerox.ddd",
        "xdw" => "application/vnd.fujixerox.docuworks",
        "xbd" => "application/vnd.fujixerox.docuworks.binder",
        "fzs" => "application/vnd.fuzzysheet",
        "txd" => "application/vnd.genomatix.tuxedo",
        "ggb" => "application/vnd.geogebra.file",
        "ggt" => "application/vnd.geogebra.tool",
        "gre" => "application/vnd.geometry-explorer",
        "gxt" => "application/vnd.geonext",
        "g2w" => "application/vnd.geoplan",
        "g3w" => "application/vnd.geospace",
        "gmx" => "application/vnd.gmx",
        "kml" => "application/vnd.google-earth.kml+xml",
        "kmz" => "application/vnd.google-earth.kmz",
        "gqs" => "application/vnd.grafeq",
        "gac" => "application/vnd.groove-account",
        "ghf" => "application/vnd.groove-help",
        "gim" => "application/vnd.groove-identity-message",
        "grv" => "application/vnd.groove-injector",
        "gtm" => "application/vnd.groove-tool-message",
        "tpl" => "application/vnd.groove-tool-template",
        "vcg" => "application/vnd.groove-vcard",
        "hal" => "application/vnd.hal+xml",
        "zmm" => "application/vnd.handheld-entertainment+xml",
        "hbci" => "application/vnd.hbci",
        "les" => "application/vnd.hhe.lesson-player",
        "hpgl" => "application/vnd.hp-hpgl",
        "hpid" => "application/vnd.hp-hpid",
        "hps" => "application/vnd.hp-hps",
        "jlt" => "application/vnd.hp-jlyt",
        "pcl" => "application/vnd.hp-pcl",
        "pclxl" => "application/vnd.hp-pclxl",
        "sfd-hdstx" => "application/vnd.hydrostatix.sof-data",
        "mpy" => "application/vnd.ibm.minipay",
        "list3820" => "application/vnd.ibm.modcap",
        "irm" => "application/vnd.ibm.rights-management",
        "sc" => "application/vnd.ibm.secure-container",
        "icm" => "application/vnd.iccprofile",
        "igl" => "application/vnd.igloader",
        "ivp" => "application/vnd.immervision-ivp",
        "ivu" => "application/vnd.immervision-ivu",
        "igm" => "application/vnd.insors.igm",
        "xpx" => "application/vnd.intercon.formnet",
        "i2g" => "application/vnd.intergeo",
        "qbo" => "application/vnd.intu.qbo",
        "qfx" => "application/vnd.intu.qfx",
        "rcprofile" => "application/vnd.ipunplugged.rcprofile",
        "irp" => "application/vnd.irepository.package+xml",
        "xpr" => "application/vnd.is-xpr",
        "fcs" => "application/vnd.isac.fcs",
        "jam" => "application/vnd.jam",
        "rms" => "application/vnd.jcp.javame.midlet-rms",
        "jisp" => "application/vnd.jisp",
        "joda" => "application/vnd.joost.joda-archive",
        "ktr" => "application/vnd.kahootz",
        "karbon" => "application/vnd.kde.karbon",
        "chrt" => "application/vnd.kde.kchart",
        "kfo" => "application/vnd.kde.kformula",
        "flw" => "application/vnd.kde.kivio",
        "kon" => "application/vnd.kde.kontour",
        "kpt" => "application/vnd.kde.kpresenter",
        "ksp" => "application/vnd.kde.kspread",
        "kwt" => "application/vnd.kde.kword",
        "htke" => "application/vnd.kenameaapp",
        "kia" => "application/vnd.kidspiration",
        "knp" => "application/vnd.kinar",
        "skm" => "application/vnd.koan",
        "sse" => "application/vnd.kodak-descriptor",
        "lasxml" => "application/vnd.las.las+xml",
        "lbd" => "application/vnd.llamagraphics.life-balance.desktop",
        "lbe" => "application/vnd.llamagraphics.life-balance.exchange+xml",
        "123" => "application/vnd.lotus-1-2-3",
        "apr" => "application/vnd.lotus-approach",
        "pre" => "application/vnd.lotus-freelance",
        "nsf" => "application/vnd.lotus-notes",
        "org" => "application/vnd.lotus-organizer",
        "scm" => "application/vnd.lotus-screencam",
        "lwp" => "application/vnd.lotus-wordpro",
        "portpkg" => "application/vnd.macports.portpkg",
        "mcd" => "application/vnd.mcd",
        "mc1" => "application/vnd.medcalcdata",
        "cdkey" => "application/vnd.mediastation.cdkey",
        "mwf" => "application/vnd.mfer",
        "mfm" => "application/vnd.mfmp",
        "flo" => "application/vnd.micrografx.flo",
        "igx" => "application/vnd.micrografx.igx",
        "mif" => "application/vnd.mif",
        "daf" => "application/vnd.mobius.daf",
        "dis" => "application/vnd.mobius.dis",
        "mbk" => "application/vnd.mobius.mbk",
        "mqy" => "application/vnd.mobius.mqy",
        "msl" => "application/vnd.mobius.msl",
        "plc" => "application/vnd.mobius.plc",
        "txf" => "application/vnd.mobius.txf",
        "mpn" => "application/vnd.mophun.application",
        "mpc" => "application/vnd.mophun.certificate",
        "xul" => "application/vnd.mozilla.xul+xml",
        "cil" => "application/vnd.ms-artgalry",
        "cab" => "application/vnd.ms-cab-compressed",
        "xlw" => "application/vnd.ms-excel",
        "xlam" => "application/vnd.ms-excel.addin.macroenabled.12",
        "xlsb" => "application/vnd.ms-excel.sheet.binary.macroenabled.12",
        "xlsm" => "application/vnd.ms-excel.sheet.macroenabled.12",
        "xltm" => "application/vnd.ms-excel.template.macroenabled.12",
        "eot" => "application/vnd.ms-fontobject",
        "chm" => "application/vnd.ms-htmlhelp",
        "ims" => "application/vnd.ms-ims",
        "lrm" => "application/vnd.ms-lrm",
        "thmx" => "application/vnd.ms-officetheme",
        "cat" => "application/vnd.ms-pki.seccat",
        "stl" => "application/vnd.ms-pki.stl",
        "pot" => "application/vnd.ms-powerpoint",
        "ppam" => "application/vnd.ms-powerpoint.addin.macroenabled.12",
        "pptm" => "application/vnd.ms-powerpoint.presentation.macroenabled.12",
        "sldm" => "application/vnd.ms-powerpoint.slide.macroenabled.12",
        "ppsm" => "application/vnd.ms-powerpoint.slideshow.macroenabled.12",
        "potm" => "application/vnd.ms-powerpoint.template.macroenabled.12",
        "mpt" => "application/vnd.ms-project",
        "docm" => "application/vnd.ms-word.document.macroenabled.12",
        "dotm" => "application/vnd.ms-word.template.macroenabled.12",
        "wdb" => "application/vnd.ms-works",
        "wpl" => "application/vnd.ms-wpl",
        "xps" => "application/vnd.ms-xpsdocument",
        "mseq" => "application/vnd.mseq",
        "mus" => "application/vnd.musician",
        "msty" => "application/vnd.muvee.style",
        "taglet" => "application/vnd.mynfc",
        "nlu" => "application/vnd.neurolanguage.nlu",
        "nitf" => "application/vnd.nitf",
        "nnd" => "application/vnd.noblenet-directory",
        "nns" => "application/vnd.noblenet-sealer",
        "nnw" => "application/vnd.noblenet-web",
        "ngdat" => "application/vnd.nokia.n-gage.data",
        "n-gage" => "application/vnd.nokia.n-gage.symbian.install",
        "rpst" => "application/vnd.nokia.radio-preset",
        "rpss" => "application/vnd.nokia.radio-presets",
        "edm" => "application/vnd.novadigm.edm",
        "edx" => "application/vnd.novadigm.edx",
        "ext" => "application/vnd.novadigm.ext",
        "odc" => "application/vnd.oasis.opendocument.chart",
        "otc" => "application/vnd.oasis.opendocument.chart-template",
        "odb" => "application/vnd.oasis.opendocument.database",
        "odf" => "application/vnd.oasis.opendocument.formula",
        "odft" => "application/vnd.oasis.opendocument.formula-template",
        "odg" => "application/vnd.oasis.opendocument.graphics",
        "otg" => "application/vnd.oasis.opendocument.graphics-template",
        "odi" => "application/vnd.oasis.opendocument.image",
        "oti" => "application/vnd.oasis.opendocument.image-template",
        "odp" => "application/vnd.oasis.opendocument.presentation",
        "otp" => "application/vnd.oasis.opendocument.presentation-template",
        "ods" => "application/vnd.oasis.opendocument.spreadsheet",
        "ots" => "application/vnd.oasis.opendocument.spreadsheet-template",
        "odt" => "application/vnd.oasis.opendocument.text",
        "odm" => "application/vnd.oasis.opendocument.text-master",
        "ott" => "application/vnd.oasis.opendocument.text-template",
        "oth" => "application/vnd.oasis.opendocument.text-web",
        "xo" => "application/vnd.olpc-sugar",
        "dd2" => "application/vnd.oma.dd2+xml",
        "oxt" => "application/vnd.openofficeorg.extension",
        "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "sldx" => "application/vnd.openxmlformats-officedocument.presentationml.slide",
        "ppsx" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
        "potx" => "application/vnd.openxmlformats-officedocument.presentationml.template",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "xltx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.template",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "dotx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
        "mgp" => "application/vnd.osgeo.mapguide.package",
        "dp" => "application/vnd.osgi.dp",
        "esa" => "application/vnd.osgi.subsystem",
        "oprc" => "application/vnd.palm",
        "paw" => "application/vnd.pawaafile",
        "str" => "application/vnd.pg.format",
        "ei6" => "application/vnd.pg.osasli",
        "efif" => "application/vnd.picsel",
        "wg" => "application/vnd.pmi.widget",
        "plf" => "application/vnd.pocketlearn",
        "pbd" => "application/vnd.powerbuilder6",
        "box" => "application/vnd.previewsystems.box",
        "mgz" => "application/vnd.proteus.magazine",
        "qps" => "application/vnd.publishare-delta-tree",
        "ptid" => "application/vnd.pvi.ptid1",
        "qxb" => "application/vnd.quark.quarkxpress",
        "bed" => "application/vnd.realvnc.bed",
        "mxl" => "application/vnd.recordare.musicxml",
        "musicxml" => "application/vnd.recordare.musicxml+xml",
        "cryptonote" => "application/vnd.rig.cryptonote",
        "cod" => "application/vnd.rim.cod",
        "rm" => "application/vnd.rn-realmedia",
        "rmvb" => "application/vnd.rn-realmedia-vbr",
        "link66" => "application/vnd.route66.link66+xml",
        "st" => "application/vnd.sailingtracker.track",
        "see" => "application/vnd.seemail",
        "sema" => "application/vnd.sema",
        "semd" => "application/vnd.semd",
        "semf" => "application/vnd.semf",
        "ifm" => "application/vnd.shana.informed.formdata",
        "itp" => "application/vnd.shana.informed.formtemplate",
        "iif" => "application/vnd.shana.informed.interchange",
        "ipk" => "application/vnd.shana.informed.package",
        "twds" => "application/vnd.simtech-mindmapper",
        "mmf" => "application/vnd.smaf",
        "teacher" => "application/vnd.smart.teacher",
        "sdkd" => "application/vnd.solent.sdkm+xml",
        "dxp" => "application/vnd.spotfire.dxp",
        "sfs" => "application/vnd.spotfire.sfs",
        "sdc" => "application/vnd.stardivision.calc",
        "sda" => "application/vnd.stardivision.draw",
        "sdd" => "application/vnd.stardivision.impress",
        "smf" => "application/vnd.stardivision.math",
        "vor" => "application/vnd.stardivision.writer",
        "sgl" => "application/vnd.stardivision.writer-global",
        "smzip" => "application/vnd.stepmania.package",
        "sm" => "application/vnd.stepmania.stepchart",
        "sxc" => "application/vnd.sun.xml.calc",
        "stc" => "application/vnd.sun.xml.calc.template",
        "sxd" => "application/vnd.sun.xml.draw",
        "std" => "application/vnd.sun.xml.draw.template",
        "sxi" => "application/vnd.sun.xml.impress",
        "sti" => "application/vnd.sun.xml.impress.template",
        "sxm" => "application/vnd.sun.xml.math",
        "sxw" => "application/vnd.sun.xml.writer",
        "sxg" => "application/vnd.sun.xml.writer.global",
        "stw" => "application/vnd.sun.xml.writer.template",
        "susp" => "application/vnd.sus-calendar",
        "svd" => "application/vnd.svd",
        "sisx" => "application/vnd.symbian.install",
        "xsm" => "application/vnd.syncml+xml",
        "bdm" => "application/vnd.syncml.dm+wbxml",
        "xdm" => "application/vnd.syncml.dm+xml",
        "tao" => "application/vnd.tao.intent-module-archive",
        "dmp" => "application/vnd.tcpdump.pcap",
        "tmo" => "application/vnd.tmobile-livetv",
        "tpt" => "application/vnd.trid.tpt",
        "mxs" => "application/vnd.triscape.mxs",
        "tra" => "application/vnd.trueapp",
        "ufdl" => "application/vnd.ufdl",
        "utz" => "application/vnd.uiq.theme",
        "umj" => "application/vnd.umajin",
        "unityweb" => "application/vnd.unity",
        "uoml" => "application/vnd.uoml+xml",
        "vcx" => "application/vnd.vcx",
        "vsw" => "application/vnd.visio",
        "vis" => "application/vnd.visionary",
        "vsf" => "application/vnd.vsf",
        "wbxml" => "application/vnd.wap.wbxml",
        "wmlc" => "application/vnd.wap.wmlc",
        "wmlsc" => "application/vnd.wap.wmlscriptc",
        "wtb" => "application/vnd.webturbo",
        "nbp" => "application/vnd.wolfram.player",
        "wpd" => "application/vnd.wordperfect",
        "wqd" => "application/vnd.wqd",
        "stf" => "application/vnd.wt.stf",
        "xar" => "application/vnd.xara",
        "xfdl" => "application/vnd.xfdl",
        "hvd" => "application/vnd.yamaha.hv-dic",
        "hvs" => "application/vnd.yamaha.hv-script",
        "hvp" => "application/vnd.yamaha.hv-voice",
        "osf" => "application/vnd.yamaha.openscoreformat",
        "osfpvg" => "application/vnd.yamaha.openscoreformat.osfpvg+xml",
        "saf" => "application/vnd.yamaha.smaf-audio",
        "spf" => "application/vnd.yamaha.smaf-phrase",
        "cmp" => "application/vnd.yellowriver-custom-menu",
        "zirz" => "application/vnd.zul",
        "zaz" => "application/vnd.zzazz.deck+xml",
        "vxml" => "application/voicexml+xml",
        "wgt" => "application/widget",
        "hlp" => "application/winhlp",
        "wsdl" => "application/wsdl+xml",
        "wspolicy" => "application/wspolicy+xml",
        "7z" => "application/x-7z-compressed",
        "abw" => "application/x-abiword",
        "ace" => "application/x-ace-compressed",
        "dmg" => "application/x-apple-diskimage",
        "vox" => "application/x-authorware-bin",
        "aam" => "application/x-authorware-map",
        "aas" => "application/x-authorware-seg",
        "bcpio" => "application/x-bcpio",
        "torrent" => "application/x-bittorrent",
        "blorb" => "application/x-blorb",
        "bz" => "application/x-bzip",
        "boz" => "application/x-bzip2",
        "cb7" => "application/x-cbr",
        "vcd" => "application/x-cdlink",
        "cfs" => "application/x-cfs-compressed",
        "chat" => "application/x-chat",
        "pgn" => "application/x-chess-pgn",
        "nsc" => "application/x-conference",
        "cpio" => "application/x-cpio",
        "csh" => "application/x-csh",
        "udeb" => "application/x-debian-package",
        "dgc" => "application/x-dgc-compressed",
        "swa" => "application/x-director",
        "wad" => "application/x-doom",
        "ncx" => "application/x-dtbncx+xml",
        "dtb" => "application/x-dtbook+xml",
        "res" => "application/x-dtbresource+xml",
        "dvi" => "application/x-dvi",
        "evy" => "application/x-envoy",
        "eva" => "application/x-eva",
        "bdf" => "application/x-font-bdf",
        "gsf" => "application/x-font-ghostscript",
        "psf" => "application/x-font-linux-psf",
        "pcf" => "application/x-font-pcf",
        "snf" => "application/x-font-snf",
        "afm" => "application/x-font-type1",
        "arc" => "application/x-freearc",
        "spl" => "application/x-futuresplash",
        "gca" => "application/x-gca-compressed",
        "ulx" => "application/x-glulx",
        "gnumeric" => "application/x-gnumeric",
        "gramps" => "application/x-gramps-xml",
        "gtar" => "application/x-gtar",
        "hdf" => "application/x-hdf",
        "install" => "application/x-install-instructions",
        "iso" => "application/x-iso9660-image",
        "jnlp" => "application/x-java-jnlp-file",
        "latex" => "application/x-latex",
        "lha" => "application/x-lzh-compressed",
        "mie" => "application/x-mie",
        "mobi" => "application/x-mobipocket-ebook",
        "application" => "application/x-ms-application",
        "lnk" => "application/x-ms-shortcut",
        "wmd" => "application/x-ms-wmd",
        "wmz" => "application/x-ms-wmz",
        "xbap" => "application/x-ms-xbap",
        "mdb" => "application/x-msaccess",
        "obd" => "application/x-msbinder",
        "crd" => "application/x-mscardfile",
        "clp" => "application/x-msclip",
        "msi" => "application/x-msdownload",
        "m14" => "application/x-msmediaview",
        "emz" => "application/x-msmetafile",
        "mny" => "application/x-msmoney",
        "pub" => "application/x-mspublisher",
        "scd" => "application/x-msschedule",
        "trm" => "application/x-msterminal",
        "wri" => "application/x-mswrite",
        "cdf" => "application/x-netcdf",
        "nzb" => "application/x-nzb",
        "pfx" => "application/x-pkcs12",
        "spc" => "application/x-pkcs7-certificates",
        "p7r" => "application/x-pkcs7-certreqresp",
        "rar" => "application/x-rar-compressed",
        "ris" => "application/x-research-info-systems",
        "sh" => "application/x-sh",
        "shar" => "application/x-shar",
        "swf" => "application/x-shockwave-flash",
        "xap" => "application/x-silverlight-app",
        "sql" => "application/x-sql",
        "sit" => "application/x-stuffit",
        "sitx" => "application/x-stuffitx",
        "srt" => "application/x-subrip",
        "sv4cpio" => "application/x-sv4cpio",
        "sv4crc" => "application/x-sv4crc",
        "t3" => "application/x-t3vm-image",
        "gam" => "application/x-tads",
        "tar" => "application/x-tar",
        "tcl" => "application/x-tcl",
        "tex" => "application/x-tex",
        "tfm" => "application/x-tex-tfm",
        "texi" => "application/x-texinfo",
        "obj" => "application/x-tgif",
        "ustar" => "application/x-ustar",
        "src" => "application/x-wais-source",
        "crt" => "application/x-x509-ca-cert",
        "fig" => "application/x-xfig",
        "xlf" => "application/x-xliff+xml",
        "xpi" => "application/x-xpinstall",
        "xz" => "application/x-xz",
        "z8" => "application/x-zmachine",
        "xaml" => "application/xaml+xml",
        "xdf" => "application/xcap-diff+xml",
        "xenc" => "application/xenc+xml",
        "xht" => "application/xhtml+xml",
        "xsl" => "application/xml",
        "dtd" => "application/xml-dtd",
        "xop" => "application/xop+xml",
        "xpl" => "application/xproc+xml",
        "xslt" => "application/xslt+xml",
        "xspf" => "application/xspf+xml",
        "xvm" => "application/xv+xml",
        "yang" => "application/yang",
        "yin" => "application/yin+xml",
        "zip" => "application/zip",
        "adp" => "audio/adpcm",
        "snd" => "audio/basic",
        "rmi" => "audio/midi",
        "mp4a" => "audio/mp4",
        "m3a" => "audio/mpeg",
        "opus" => "audio/ogg",
        "s3m" => "audio/s3m",
        "sil" => "audio/silk",
        "uvva" => "audio/vnd.dece.audio",
        "eol" => "audio/vnd.digital-winds",
        "dra" => "audio/vnd.dra",
        "dts" => "audio/vnd.dts",
        "dtshd" => "audio/vnd.dts.hd",
        "lvp" => "audio/vnd.lucent.voice",
        "pya" => "audio/vnd.ms-playready.media.pya",
        "ecelp4800" => "audio/vnd.nuera.ecelp4800",
        "ecelp7470" => "audio/vnd.nuera.ecelp7470",
        "ecelp9600" => "audio/vnd.nuera.ecelp9600",
        "rip" => "audio/vnd.rip",
        "weba" => "audio/webm",
        "aac" => "audio/x-aac",
        "aifc" => "audio/x-aiff",
        "caf" => "audio/x-caf",
        "flac" => "audio/x-flac",
        "mka" => "audio/x-matroska",
        "m3u" => "audio/x-mpegurl",
        "wax" => "audio/x-ms-wax",
        "wma" => "audio/x-ms-wma",
        "ra" => "audio/x-pn-realaudio",
        "rmp" => "audio/x-pn-realaudio-plugin",
        "wav" => "audio/x-wav",
        "xm" => "audio/xm",
        "cdx" => "chemical/x-cdx",
        "cif" => "chemical/x-cif",
        "cmdf" => "chemical/x-cmdf",
        "cml" => "chemical/x-cml",
        "csml" => "chemical/x-csml",
        "xyz" => "chemical/x-xyz",
        "ttc" => "font/collection",
        "otf" => "font/otf",
        "ttf" => "font/ttf",
        "woff" => "font/woff",
        "woff2" => "font/woff2",
        "bmp" => "image/bmp",
        "cgm" => "image/cgm",
        "g3" => "image/g3fax",
        "gif" => "image/gif",
        "ief" => "image/ief",
        "jpe" => "image/jpeg",
        "ktx" => "image/ktx",
        "png" => "image/png",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "btif" => "image/prs.btif",
        "sgi" => "image/sgi",
        "svgz" => "image/svg+xml",
        "tif" => "image/tiff",
        "psd" => "image/vnd.adobe.photoshop",
        "uvvg" => "image/vnd.dece.graphic",
        "djv" => "image/vnd.djvu",
        "sub" => "image/vnd.dvb.subtitle",
        "dwg" => "image/vnd.dwg",
        "dxf" => "image/vnd.dxf",
        "fbs" => "image/vnd.fastbidsheet",
        "fpx" => "image/vnd.fpx",
        "fst" => "image/vnd.fst",
        "mmr" => "image/vnd.fujixerox.edmics-mmr",
        "rlc" => "image/vnd.fujixerox.edmics-rlc",
        "mdi" => "image/vnd.ms-modi",
        "wdp" => "image/vnd.ms-photo",
        "npx" => "image/vnd.net-fpx",
        "wbmp" => "image/vnd.wap.wbmp",
        "xif" => "image/vnd.xiff",
        "webp" => "image/webp",
        "3ds" => "image/x-3ds",
        "ras" => "image/x-cmu-raster",
        "cmx" => "image/x-cmx",
        "fh7" => "image/x-freehand",
        "ico" => "image/x-icon",
        "sid" => "image/x-mrsid-image",
        "pcx" => "image/x-pcx",
        "pct" => "image/x-pict",
        "pnm" => "image/x-portable-anymap",
        "pbm" => "image/x-portable-bitmap",
        "pgm" => "image/x-portable-graymap",
        "ppm" => "image/x-portable-pixmap",
        "rgb" => "image/x-rgb",
        "tga" => "image/x-tga",
        "xbm" => "image/x-xbitmap",
        "xpm" => "image/x-xpixmap",
        "xwd" => "image/x-xwindowdump",
        "mime" => "message/rfc822",
        "iges" => "model/iges",
        "silo" => "model/mesh",
        "dae" => "model/vnd.collada+xml",
        "dwf" => "model/vnd.dwf",
        "gdl" => "model/vnd.gdl",
        "gtw" => "model/vnd.gtw",
        "mts" => "model/vnd.mts",
        "vtu" => "model/vnd.vtu",
        "vrml" => "model/vrml",
        "x3dbz" => "model/x3d+binary",
        "x3dvz" => "model/x3d+vrml",
        "x3dz" => "model/x3d+xml",
        "appcache" => "text/cache-manifest",
        "ifb" => "text/calendar",
        "css" => "text/css",
        "csv" => "text/csv",
        "htm" => "text/html",
        "n3" => "text/n3",
        "in" => "text/plain",
        "dsc" => "text/prs.lines.tag",
        "rtx" => "text/richtext",
        "sgm" => "text/sgml",
        "tsv" => "text/tab-separated-values",
        "ms" => "text/troff",
        "ttl" => "text/turtle",
        "urls" => "text/uri-list",
        "vcard" => "text/vcard",
        "curl" => "text/vnd.curl",
        "dcurl" => "text/vnd.curl.dcurl",
        "mcurl" => "text/vnd.curl.mcurl",
        "scurl" => "text/vnd.curl.scurl",
        "fly" => "text/vnd.fly",
        "flx" => "text/vnd.fmi.flexstor",
        "gv" => "text/vnd.graphviz",
        "3dml" => "text/vnd.in3d.3dml",
        "spot" => "text/vnd.in3d.spot",
        "jad" => "text/vnd.sun.j2me.app-descriptor",
        "wml" => "text/vnd.wap.wml",
        "wmls" => "text/vnd.wap.wmlscript",
        "asm" => "text/x-asm",
        "dic" => "text/x-c",
        "f90" => "text/x-fortran",
        "java" => "text/x-java-source",
        "nfo" => "text/x-nfo",
        "opml" => "text/x-opml",
        "pas" => "text/x-pascal",
        "etx" => "text/x-setext",
        "sfv" => "text/x-sfv",
        "uu" => "text/x-uuencode",
        "vcs" => "text/x-vcalendar",
        "vcf" => "text/x-vcard",
        "3gp" => "video/3gpp",
        "3g2" => "video/3gpp2",
        "h261" => "video/h261",
        "h263" => "video/h263",
        "h264" => "video/h264",
        "jpgv" => "video/jpeg",
        "jpgm" => "video/jpm",
        "mjp2" => "video/mj2",
        "mpg4" => "video/mp4",
        "m2v" => "video/mpeg",
        "ogv" => "video/ogg",
        "mov" => "video/quicktime",
        "uvvh" => "video/vnd.dece.hd",
        "uvvm" => "video/vnd.dece.mobile",
        "uvvp" => "video/vnd.dece.pd",
        "uvvs" => "video/vnd.dece.sd",
        "uvvv" => "video/vnd.dece.video",
        "dvb" => "video/vnd.dvb.file",
        "fvt" => "video/vnd.fvt",
        "m4u" => "video/vnd.mpegurl",
        "pyv" => "video/vnd.ms-playready.media.pyv",
        "uvvu" => "video/vnd.uvvu.mp4",
        "viv" => "video/vnd.vivo",
        "webm" => "video/webm",
        "f4v" => "video/x-f4v",
        "fli" => "video/x-fli",
        "flv" => "video/x-flv",
        "m4v" => "video/x-m4v",
        "mks" => "video/x-matroska",
        "mng" => "video/x-mng",
        "asx" => "video/x-ms-asf",
        "vob" => "video/x-ms-vob",
        "wm" => "video/x-ms-wm",
        "wmv" => "video/x-ms-wmv",
        "wmx" => "video/x-ms-wmx",
        "wvx" => "video/x-ms-wvx",
        "avi" => "video/x-msvideo",
        "movie" => "video/x-sgi-movie",
        "smv" => "video/x-smv",
        "ice" => "x-conference/x-cooltalk"
    ];

}
