<?php
class app extends \tabler {
    use \openapi;

    public static $openapiOnly = ['index'];

    public static function index($data=[]) {
        //url /app will show all the css(), html() and js() functions from this folder
        exit(str_replace('<title></title>','<title>Project</title>',parent::index(__CLASS__))); //__CLASS__ being /app folder (see \assets for more details)
    }

    public static function log(...$params) {
        //implement a log function
        return true;
    }

    public static function css($data=[]) { ?><style>

            :root {
                --maincolor: #2b11d6;
                --maincolor2: #f0f0f0;
                --maincolor3: #262626;
                --maincolor4: #898989;
                --maincolor5: #151515;

                --text: #f0f0f0;
                --icons: #898989;

                --background: #151515;
                --cardbackground: #262626;
            }

            a { color:var(--maincolor); text-decoration:none; }

            span { color:var(--maincolor4); }

            b { color:var(--text); font-weight:600; }            

            html,body {
                background-color:var(--background);
                border:0px solid var(--background);
                color:var(--text);
                font-size:14px;
            }

            *:not(input) {
                -webkit-touch-callout: none; 
                -webkit-user-select: none; 
                -khtml-user-select: none; 
                -moz-user-select: none; 
                -ms-user-select: none; 
                user-select: none;
            }

            .screen { padding-bottom:4rem; overflow:hidden; }

            .card {
                background-color:var(--cardbackground);
                border:1px solid var(--cardbackground);
                border-radius:20px;
                color:var(--text);
                padding:2rem;
                width:100%;
                display:block;
            }

            .content {
                padding: 0 2rem;
            }

            .background-gradient {
                background-color: #000;
                background-image: radial-gradient(
                    at top right,
                    rgba(3, 170, 84, 0.7) 0%,
                    rgba(3, 170, 84, 0.3) 25%,
                    rgba(3, 170, 84, 0.02) 50%,
                    transparent 75% ),
                    radial-gradient(
                        at bottom left,
                        rgba(3, 170, 84, 0.7) 0%,
                        rgba(3, 170, 84, 0.3) 25%,
                        rgba(3, 170, 84, 0.02) 50%,
                        transparent 75% );
                background-size: 100% 100%;
                background-repeat: no-repeat;
                height: 100%; height: 100vh;
                min-height: 100%; min-height: 100vh;
            }

            button, input, .btn, .btn1, .btn2, .btn3, 
            .btns, .btns1, .btns2, .btns3, .btn-primary {
                font-family: Poppins !important;
                background-color:var(--maincolor);
                color:var(--maincolor5);
                border-radius:50px;
                text-align:center;
                width:100%;
                margin:auto;
                padding:12px;
                font-weight:600;
                border:0px solid var(--maincolor);
            }

            .btn2, .btns2, .btn-secundary {
                color:#fff;
                background-color:transparent;
                border:2px solid var(--maincolor);
            }

            .btn3, .btns3, .btn-dark {
                color:#fff;
                background-color:var(--maincolor3);
                border:2px solid #333;
            }

            .btn.round, .btn1.round,
            .btn2.round, .btn3.round {
                display:inline-block;
                border-radius:50%;
                line-height: 0px;
                width: 46px;
                height: 46px;
                margin: 0.5rem 0px 0.5rem 0px;
            }

            button[icon] { display:none; }

            .btnfull {
                display: flex;
                height: 4rem;
                align-items: center;
                background-color: var(--cardbackground);
                border: 1px #999;
                border-radius: 15px;
                margin: 1rem auto;
            }
            .btnfull .colicon {
                color: var(--maincolor);
                justify-content: left;
                align-items: center;
                margin: 0px 1.5rem;
                flex: 0 0 auto;
            }
            .btnfull .collabel {
                font-size: 1rem;
                flex: 1;
            }
            .btnfull .colarrow {
                color: #ddd;
                font-size: 2.5rem;
                margin: 0px 1.75rem auto 0px;
                flex: 0 0 auto;
            }

            button:active, a:active, input[type=button]:active,
            .btnfull:active, .boxoption:active { opacity:0.7; }

            input:not([type=button]):not([type=submit]) {
                color:#fff;
                background-color:transparent;
                border-radius:0; border-top:0; border-left:0; border-right:0; 
                border-bottom:1px solid var(--maincolor4);
                width:calc(100% - 24px);
                outline: 0;
                padding:12px;
                text-align:left;
                margin:0px;
            }

            input.fullborder {
                border:1px solid var(--maincolor4) !important;
                border-radius:50px !important;
            }

            [data-animate] { filter:opacity(0); transition: .4s; }
            [data-animate="up"] { transform: translate3d(0, +70px, 0); }
            [data-animate="left"] { transform: translate3d(-70px, 0, 0); }
            [data-animate="right"] { transform: translate3d(+70px, 0, 0); }
            [data-animate].animate { filter:opacity(1) !important; transform: translate3d(0,0,0); }

            @keyframes shake {
              10%, 90% { transform: translate3d(-1px, 0, 0); } 20%, 80% { transform: translate3d(2px, 0, 0); }
              30%, 50%, 70% { transform: translate3d(-4px, 0, 0); } 40%, 60% { transform: translate3d(4px, 0, 0); } }
              
            .shake, .terremoto { 
                animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
                transform: translate3d(0, 0, 0); backface-visibility: hidden; perspective: 1000px; 
            }

            .backbtn { font-size:34px; color: var(--icons); padding:1rem; align-items: center; display: flex; }
            .backbtn .bttitle { font-size: 13px; margin-left: 10px; vertical-align: middle; }

            select {
                display:inline-block;
                color:var(--maincolor4);
                border:0px solid transparent;
                background-color:transparent;
                text-align:middle;
                max-width:50%;
                padding:0px;
                margin:0px;
                outline:0px;
                box-shadow: none;
                -webkit-user-select: none;
            }

            .dotarea {
                display: flex;
                justify-content: center;
                margin:0rem 1rem 0rem 1rem;
            }

            .dotarea > div {
                background-color: var(--maincolor4);
                color:var(--maincolor4);
                border-radius:2px;
                font-size:1px;
                margin: 0px 10px 2rem 10px;
                padding: 2px;
            }
            
            .dotarea > div.active { 
                background-color: var(--maincolor); 
                color:var(--maincolor);
            }

            .dot {
                display:inline-block;
                background-color: var(--maincolor);
                color:var(--maincolor);
                vertical-align:middle;
                border-radius:4px;
                font-size:1px;
                margin: 0px 0.75rem 0px 0px;
                padding: 4px;
                height:8px;
                width:8px;
            }

            .progress {
                position: relative;
                height: 4px;
                display: block;
                width: 100%;
                background-color: var(--maincolor3);
                border-radius: 5px;
                margin: 20px auto;
                overflow: hidden;
            }

            .progress > .indeterminate {
                background-color: var(--maincolor);
                width: 100%;
                height: 100%;
                position: absolute;
                animation: indeterminatepreloader 1s infinite;
            }

            @keyframes indeterminatepreloader {
                0% { left: -100%; right: 100%; }
                50% { left: 0; right: 0; }
                100% { left: 100%; right: -100%; }
            }

            .progress > .determinate {
                background-color: var(--maincolor);
                height: 100%;
                width: 0;
                transition: width 0.4s linear;
            }

            .info-list { list-style-type: none; padding: 0; margin: 0; }
            .info-list .info-item { display: flex; align-items: center; margin-bottom: 15px; padding:10px 0px; border-radius: 5px; transition: background-color 0.3s ease; }
            .info-list.spaced .info-item { padding:10px; }
            .info-list .info-item.completed { background-color: #1b5e20; }
            .info-list .info-item.active { background-color: #0d47a1; }
            .info-list .info-item .info-icon { flex: 0 0 auto; width: 24px; height: 24px; font-size:24px; margin-right: 1rem; background-size: cover; }
            .info-list .info-item .info-icon-completed { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%234CAF50"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>'); }
            .info-list .info-item .info-icon-pending { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23757575"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); }
            .info-list .info-item .info-text { flex:1 1 auto; min-width: 0; padding-right: 10px; }
            .info-list .info-item .info-text div { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .info-list .info-item .info-status { font-size: 0.8em; color: #9e9e9e; }
            .info-list .info-item .info-action-icon { flex: 0 0 auto; margin-left: auto; width: 24px; height: 24px; background-size: cover; cursor: pointer; opacity: 0.7; transition: opacity 0.3s ease; }
            .info-list .info-item .info-icon-edit { width: 16px; height: 16px; background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FFFFFF"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>'); }
            .info-list .info-item .info-icon-next { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FFFFFF"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>'); }
            .info-icon-circle {
                --icon-color: #757575;
                -webkit-mask: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>') no-repeat center;
                mask: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>') no-repeat center;
                background-color: var(--icon-color);
                -webkit-mask-size: contain;
                mask-size: contain;
                width: 24px;
                height: 24px;
                display: inline-block;
            }
            .info-icon-circle.active {
                --icon-color: var(--maincolor);
                -webkit-mask: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><circle cx="12" cy="12" r="7"/></svg>') no-repeat center;
                mask: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><circle cx="12" cy="12" r="7"/></svg>') no-repeat center;
            }

            .inputlist-p p { display:block; margin:1rem 0px 3rem 0px; }
            .inputlist-p p input:not(.unpad) { padding-left:0px !important; }

            #tkeqrcomponent { border:0px solid transparent !important; }
        </style><?php
        echo parent::css();
    }

    public static function js($data=[]) { ?><script>
        $(window).on('screen_onstart',function(state){ 
            $('button[icon]').each(function(){ 
                let bitem = this;
                let bonclick = empty($(bitem).attr('onclick'),'');
                let bclass = empty($(bitem).attr('class'),'');
                let bicon = empty($(bitem).attr('icon'),'');
                let bid = empty($(bitem).attr('id'),'');
                let btext = empty($(bitem).html(),'');
                $(`<div id="${bid}" class="btnfull ${bclass}" onclick="${bonclick}">
                    <div class="colicon"><i class="${bicon}"></i></div>
                    <div class="collabel">${btext}</div>
                    <div class="colarrow">&#8250;</div>
                </div>`).insertAfter(bitem);
                $(bitem).remove();
            });
        });

        $(window).on('screen_onload',function(state){ 
            if($(state.to+' .backbtn').length)
                $(state.to+' .backbtn').html('<div style="padding:0px 1rem;font-size:30px;"><span class="encolor">&#8249;</span><span class="encolor bttitle">Voltar</span></div>');
        });

        </script><?php
        echo parent::js();
    }

}