$(window).on('pushsubscribe',function(state){
    let channel = String(state.to).replace('#','');
    let uid = String(getitem('uid'));
    try { 
        if(!empty(channel)) FirebasePlugin.subscribe("topic"+channel);
        if(!empty(uid) && (uid !== channel)) FirebasePlugin.subscribe("topic"+uid);
        FirebasePlugin.subscribe("topicall");
        FirebasePlugin.subscribe("topic"+((thisisandroid)?"android":"ios"));
    } catch(e) { console.log('[firebase] Could not subscribe to push'); }
});

$(window).on('pushunsubscribe',function(state){
    let channel = String(state.to).replace('#','');
    try { FirebasePlugin.unsubscribe("topic"+channel);
    } catch(e) { console.log('[firebase] Could not unsubscribe to channel'); }
});

$(window).on('pushid',function(state){
    let token = state.token;
    setitem('@pushid',token);
    console.log('[firebase] Push token id set to: '+token);
    eventfire('pushsubscribe',{});
});

$(window).on("screen_onload",function(state){
    var screenname = String(state.to).replace(/[^0-9A-Za-z]/gi,'');
    try { FirebasePlugin.setScreenName(screenname); } catch(e) { }
    try { FirebasePlugin.logEvent("select_content", {content_type: "page_view", item_id: screenname}); } catch(e) { }
});

$(window).on('onload',function(){
    try { FirebasePlugin.grantPermission(function(){ eventfire('pushsubscribe',{}); }); } catch(e) { }

    try { FirebasePlugin.getToken(function(token){ eventfire('pushid',{'token':token}); }); } catch(e) { }

    try { FirebasePlugin.onTokenRefresh(function(token){ eventfire('pushid',{'token':token}); }); } catch(e) { }
    
    try { FirebasePlugin.onMessageReceived(function(data){ eventfire('pushnotification',{ 'msg':data }); }); } catch(e) { }

    eventfire('pushsubscribe',{});
});