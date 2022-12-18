<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\msgController;


//insert,delete message route
Route::post('/InsertDelete', function(Request $request){ 
    
        date_default_timezone_set("Europe/Tirane");
        $current_time = Carbon\Carbon::now()->format('Y-m-d H:i:s.v');
    
        $verifyToken = true; // $request->input('userToken') duhet me verifiku qe useri eshte prof/asist
        if($verifyToken){
        $sender_id_table =  $request->input('sender_id').'_t';
        $error = false;
        DB::beginTransaction();
        if($request->input('notificationType') == "newMsg"){
                $values = array('sender_emri' => $request->input('senderEmri'),
                            'sender_id' => $request->input('sender_id'),
                            'topic' => $request->input('topic'),
                            'msg' => $request->input('msg'),
                            'koha' => $current_time,
                            'email_sent' => $request->input('emailData'));
    
                   $id = DB::table('fshk_messages')->insertGetId($values);
                   if( empty($id) ) {
                        DB::rollBack();
                        return json_encode(array("status"=>"error"));
                   }
                 //Firebase push notification
               $message = array('notificationType'=>$request->input('notificationType'),'sender_emri'=>$request->input('senderEmri'),'msg'=>$request->input('msg'),'koha'=>$current_time,'topic'=>$request->input('topic'),'sender_id'=>$request->input('sender_id'));
        
                $url = "https://fcm.googleapis.com/fcm/send";
                $fields = array(
                'to'=>'/topics/'.$request->input('topic'),
                'data' => $message,
                'priority'=> 'high'
                );
                $headers = array(
                'Authorization:key = AAAAJUC4DRQ:APA91bGr4kjMwSXIYgm9cNYCxvw2CMq3cIUUmgJtWyqqOwghbb9EslbysCARQuaR4nHebcEh4alx0sBGDvi0xM6FaLH3pIgy73_UCDlkjyf8jiwMVNBMQ1AhsHLaIAcq-mVEclwYNxga',
                'Content-Type:application/json',
                );
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_POST,true);
                curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
                curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
                curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($fields));
                curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
               
            
                //oneSignal  
                 $content = array(
                    "en" => $current_time
                    );
            
                $fields = array(
                    'app_id' => "529fb3a4-d1f1-4304-9548-e04045196724",
                    'included_segments' => array('All'),
                    'data' => $message,
                    'contents' => $content
                );
            
                $fields = json_encode($fields);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                          'Authorization: Basic ZGU5YzkzYzctNDE5ZS00ZDJhLWI5NTktMTMxZDM4YWI0OTgx'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    
                curl_exec($ch);
                $httpcode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if($httpcode == 200 || $httpcode2 == 200){
                    DB::commit(); 
                    // schedule email 
                    return json_encode(array("koha"=>$current_time));
                }
                    DB::rollBack();
                    return json_encode(array("koha"=>"error"));
       }
        else{
            //delete message
            
            
          $query =    DB::table('fshk_messages')
            ->where('koha', $request->input('koha'))
            ->delete(); 
           DB::commit(); 
            
             $message = array('notificationType'=>'deleteMsg','koha'=>$request->input('koha'),'sender_id'=>$request->input('sender_id'));
        
                $url = "https://fcm.googleapis.com/fcm/send";
                $fields = array(
                'to'=>'/topics/'.$request->input('topic'),
                'data' => $message,
                'priority'=> 'high'
                );
                $headers = array(
                'Authorization:key = AAAAJUC4DRQ:APA91bGr4kjMwSXIYgm9cNYCxvw2CMq3cIUUmgJtWyqqOwghbb9EslbysCARQuaR4nHebcEh4alx0sBGDvi0xM6FaLH3pIgy73_UCDlkjyf8jiwMVNBMQ1AhsHLaIAcq-mVEclwYNxga',
                'Content-Type:application/json',
                );
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_POST,true);
                curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
                curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
                curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($fields));
                curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            return 'deleted';
        }
        }
        //return DB::table("fshk_receivers")->get();
});
Route::post('/sendSeen', function(Request $request){
    	$JSONObject = array("notificationType"=>"updateSeenMsg","msgKoha_arr"=>json_decode($request->input('msgKoha_arr')),"receiver"=> $request->input('receiver'),"receiver_id"=> $request->input('receiver_id'));
    	
        $senderTokens = DB::Table("fshk_tokens")->select('token')->where('user_id',$request->input('sender_id'))->get() ->map(function ($item) {
                return $item->token;
          });
      if(count($senderTokens)>0){
        $url = "https://fcm.googleapis.com/fcm/send";
    	$fields = array(
    	'registration_ids'=>$senderTokens,
    	'data' => $JSONObject,
    	'priority'=> 'high'
    	);
    	$headers = array(
    	'Authorization:key = AAAAJUC4DRQ:APA91bGr4kjMwSXIYgm9cNYCxvw2CMq3cIUUmgJtWyqqOwghbb9EslbysCARQuaR4nHebcEh4alx0sBGDvi0xM6FaLH3pIgy73_UCDlkjyf8jiwMVNBMQ1AhsHLaIAcq-mVEclwYNxga',
    	'Content-Type:application/json',
    	);
    	$ch = curl_init();
    	curl_setopt($ch,CURLOPT_URL,$url);
    	curl_setopt($ch,CURLOPT_POST,true);
    	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
    	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    	curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($fields));
        $results = curl_exec($ch);
        echo '<br><br>'.$results;
        
      }     
        $receiverEmri = $request->input('receiver');
        $receiverID = $request->input('receiver_id');
        $msgKoha_arr = json_decode($request->input('msgKoha_arr')); 
        $seenTime_arr = json_decode($request->input('seenTime_arr')); 
        $fromS_array = json_decode($request->input('fromS_array')); 
        
        date_default_timezone_set("Europe/Tirane");
        $current_time = date('Y:m:d H:i:s');

        $last_msg_koha = $msgKoha_arr[0];
        
        
        $values = array();
        for ($i = 0; $i < count($msgKoha_arr); $i++) {
            if(strtotime($seenTime_arr[$i]) > strtotime($current_time)) $seenTime_arr[$i] = $current_time;
            $msgReceivedFromService = "Firebase";
            switch($fromS_array[$i]){
                case 'O':
                    $msgReceivedFromService = "OneSignal";
                    break;
                case 'MR':
                    $msgReceivedFromService = "Message Request";
                    break;
                case 'GM':
                    $msgReceivedFromService = "Get Messages";
                    break;
                    
            }
             $values[$i] = array(
                        'receiver_emri' => $receiverEmri,
                        'receiver_id' => $receiverID,
                        'msgKoha' => $msgKoha_arr[$i],
                        'seen_time' => $seenTime_arr[$i],
                        'from_service' => $msgReceivedFromService);
             
             if($msgKoha_arr[$i]>$last_msg_koha) $last_msg_koha = $msgKoha_arr[$i];
        }
        DB::table('fshk_msgSeenTime')->insertOrIgnore($values);
        
        $last_seen_ondb = DB::table('fshk_receivers')->select('id')->where('last_seen_msg','>',$last_msg_koha)->where(['receiver_id'=>$request->input('receiver_id'),'sender_id'=>$request->input('sender_id')])->first();
        echo $request->input('orariChange');
        if($request->input('orariChange') == "false" && $last_seen_ondb == null){
            echo "here";
            DB::table('fshk_receivers')->updateOrInsert(['receiver_id'=>$request->input('receiver_id'),'sender_id'=>$request->input('sender_id')],['sender_emri'=>$request->input('sender_emri'),'last_seen_msg'=>$last_msg_koha]);     
        }
     //test   DB::table('fshk_msgSeenTime')->upsert($data,['receiver_emri','msgKoha'],[]);     
});

Route::post('/loadMoreMsgs', function(Request $request){
    $sender_id = $request->input('sender_id');
    
    if($request->input('msgsType') == "received"){
        $topics = json_decode($request->input('topics'));
         if($request->input('koha') == 'null') 
            return DB::Table('fshk_messages')->select('msg','koha','topic')->where('msg','!=','orariChange')->where('sender_id',$sender_id)->whereIn('topic', $topics)->orderBy('koha', 'DESC')->limit(3)->get();
         else
            return DB::Table('fshk_messages')->select('msg','koha','topic')->where('koha','<',$request->input('koha'))->where('msg','!=','orariChange')->where('sender_id',$sender_id)->whereIn('topic', $topics)->orderBy('koha', 'DESC')->limit(3)->get();
         return json_encode(array());
    }
    else{ //sent
        $topic =$request->input('topic');
        if($request->input('koha') == 'null')
            return DB::Table('fshk_messages')->select('msg','koha')->where('topic',$topic)->where('sender_id',$sender_id)->orderBy('koha', 'DESC')->limit(3)->get();
        else
            return DB::Table('fshk_messages')->select('msg','koha')->where('topic',$topic)->where('sender_id',$sender_id)->where('koha',"<",$request->input('koha'))->orderBy('koha', 'DESC')->limit(3)->get();
         return json_encode(array());
    }
});
Route::post('/NewSentMsgs', function(Request $request){
    return json_encode(DB::Table('fshk_messages')->select('msg','koha')->where([['topic',$request->input('topic')],['koha','>',$request->input('koha')],['sender_id',$request->input('sender_id')]])->orderBy('koha', 'DESC')->get()); 
});

Route::post('/getMessages', function(Request $request){
    $topics = json_decode($request->input('topics'));
    $koha =$request->input('koha');
    
    date_default_timezone_set("Europe/Tirane");
    $current_time = Carbon\Carbon::now()->format('Y-m-d H:i:s.v');
      
    $msgs =  DB::Table('fshk_messages')->select('sender_emri','sender_id','topic','msg','koha')->whereIn('topic', $topics)->where('koha','>',$koha)->orderBy('koha', 'DESC')->get();
    
    return array("currentTime"=>$current_time,"msgs"=>$msgs);
    
    
});
Route::post('/getAllMessages', function(Request $request){
    $topics = json_decode($request->input('topics'));
    $user_id =$request->input('user_id');

    $objs1 = DB::Table('fshk_receivers')->select('sender_emri','sender_id','last_seen_msg')->where('receiver_id',$user_id)->get();
    
    $senders = array();
    $last_msg_seen = array();
    $n =0;
    foreach($objs1 as $obj){
        $senders[$n] = $obj->sender_id;
        $last_msg_seen[$n] = $obj->last_seen_msg;
        $n++;
    }
     if(count($senders)>0){
          $sql = '';
        for($j=0;$j<count($senders);$j++){
              $sender = $senders[$j];
              $last_seen = $last_msg_seen[$j];
              if($sql == '')
                $sql = "SELECT sender_emri,sender_id,topic,msg,koha FROM fshk_messages WHERE topic IN ('".implode("','",$topics)."') AND sender_id = '$sender' AND msg != 'orariChange'  AND koha >= '$last_seen'";
              else
                $sql .= " UNION SELECT sender_emri,sender_id,topic,msg,koha FROM fshk_messages WHERE topic IN ('".implode("','",$topics)."') AND sender_id = '$sender' AND msg != 'orariChange' AND koha >= '$last_seen'";
        }
         $sql .= " UNION SELECT sender_emri,sender_id,topic,msg,koha FROM fshk_messages WHERE topic IN ('".implode("','",$topics)."') AND sender_id NOT IN ('".implode("','",$senders)."') AND msg != 'orariChange' ";
         $sql .= " ORDER BY koha DESC";
        
        $receivedMsgs = DB::select(DB::raw($sql));
        return  array("receivedMsgs"=>$receivedMsgs,"lastSeenMsgs"=>$objs1);
     }
     else {
      $receivedMsgs =  DB::Table('fshk_messages')->select('sender_emri','sender_id','topic','msg','koha')->whereIn('topic', $topics)->where('msg','!=','orariChange')->get();
         return array("receivedMsgs"=>$receivedMsgs,"lastSeenMsgs"=>array());
     }
});
Route::post('/getAllMessagesTest', function(Request $request){
    $topics = json_decode($request->input('topics'));
    $user_id =$request->input('user_id');

    $objs1 = DB::Table('fshk_receivers')->select('sender_emri','sender_id','last_seen_msg')->where('receiver_id',$user_id)->get();
    
    
    $n =0;
    $msgs = [];
    $senders = array();
    foreach($objs1 as $obj){
         $object = new stdClass();
         $object->sender_id = $obj->sender_id;
         $object->sender_emri = $obj->sender_emri;
         $object->last_seen_msg = $obj->last_seen_msg;
         $object->sender_messages = DB::Table('fshk_messages')->select('topic','msg','koha')->where('koha','>=',$obj->last_seen_msg)->whereIn('topic',$topics)->where('msg','!=','orariChange')->where('sender_id',$obj->sender_id)->get();
         for($i=0;$i<count($object->sender_messages);$i++){

            if($object->sender_messages[$i]->Koha > $obj->last_seen_msg) 
                $object->sender_messages['newMessage'] = true;
            else
                $object->sender_messages['newMessage'] = false;
         }   
        array_push($msgs,  $object);
        $senders[$n] = $obj->sender_id;
        $n++;
    }
    
    $objs2 = DB::Table('fshk_messages')->select('sender_emri','sender_id')->whereIn('topic',$topics)->whereNotIn('sender_id',$senders)->distinct()->get();
    
    
     foreach($objs2 as $obj2){
         $object = new stdClass();
         $object->sender_id = $obj2->sender_id;
         $object->sender_emri = $obj2->sender_emri;
         $object->last_seen_msg = '-';
         $object->sender_messages = DB::Table('fshk_messages')->select('topic','msg','koha')->whereIn('topic',$topics)->where('msg','!=','orariChange')->where('sender_id',$obj2->sender_id)->get();
           array_push($msgs,  $object);
        $senders[$n] = $obj2->sender_id;
        $n++;
    }
    
    return $msgs;
    
});
Route::post('/getSeenList', function(Request $request){
     return DB::Table('fshk_msgSeenTime')->select('receiver_emri','seen_time')->where('msgKoha',$request->input('koha'))->get();
});
Route::post('/insertToken', function(Request $request){
        DB::table('fshk_tokens')->updateOrInsert(['token'=>$request->input('token')],['user_id'=>$request->input('user_id'),'token'=>$request->input('token'),'user_emri'=>$request->input('user_emri'),'device'=>$request->input('device')]);     
});
Route::post('/getMsgsByTopic', function(Request $request){
    $topic = $request->input('topic');
    $senders = array();
    return DB::Table('fshk_messages')->select('sender_emri','msg','koha')->where('topic', $topic)->where('msg','!=','orariChange')->get();
});
Route::get('/getLajmet', function(Request $request){
    $lajmet = DB::table('lajmet')->get();
    return $lajmet;
});

Route::post('/reportError', function(Request $request){
    date_default_timezone_set("Europe/Tirane");
        
    $values = array('user_id' => $request->input('user_id'),
                    'user_name' => $request->input('user_name'),
                    'error' => $request->input('error'),
                    'created_at' => Carbon\Carbon::now()->format('Y-m-d H:i:s.v'));
                            
    
    DB::table('error_reporter')->insert($values);
    
    
});

Route::get('/getFilters', function(Request $request){
    date_default_timezone_set('Europe/Tirane');
    $jayParsedAry = [
   "currentTime" => date("Y-m-d H:i:sa"), 
   "fakulteti" => [
         "emri" => "Shkenca Kompjuterike", 
         "gradat" => [
            [
               "grada" => "Master", 
               "departamentet" => [
                  [
                     "departamenti" => "Shkenca Kompjuterike", 
                     "programet" => [
                        [
                           "programi" => "Sistemet e Kontrollit dhe Inteligjenca Artificiale - 2021", 
                           "semestrat" => [
                              [
                                 "semestri" => 1, 
                                 "lendet" => [
                                    [
                                       "grupetL" => 1, 
                                       "grupetU" => 1, 
                                       "emri" => "Aproksimimet e Aplikuara ne AI dhe Robotike" 
                                    ], 
                                    [
                                          "grupetL" => 1, 
                                          "grupetU" => 1, 
                                          "emri" => "Bazat e Inteligjences Artificiale" 
                                       ], 
                                    [
                                             "grupetL" => 1, 
                                             "grupetU" => 1, 
                                             "emri" => "Programimi PLC" 
                                          ], 
                                    [
                                                "grupetL" => 1, 
                                                "grupetU" => 1, 
                                                "emri" => "Teoria e Kontrolles dhe Praktika" 
                                             ], 
                                    [
                                                   "grupetL" => 1, 
                                                   "grupetU" => 1, 
                                                   "emri" => "Deep Learning - Metodat dhe Aplikimet" 
                                                ], 
                                    [
                                                      "grupetL" => 1, 
                                                      "grupetU" => 1, 
                                                      "emri" => "Kontrolla e Adaptueshme" 
                                                   ], 
                                    [
                                                         "grupetL" => 1, 
                                                         "grupetU" => 1, 
                                                         "emri" => "Mekatronika" 
                                                      ], 
                                    [
                                                            "grupetL" => 1, 
                                                            "grupetU" => 1, 
                                                            "emri" => "Algoritmet dhe Inteligjenca Artificiale" 
                                                         ] 
                                 ] 
                              ] 
                           ], 
                           "specializimet" => [
                                                            ] 
                        ], 
                        [
                                                                  "programi" => "E-Qeverisja - 2021", 
                                                                  "semestrat" => [
                                                                     [
                                                                        "semestri" => 1, 
                                                                        "lendet" => [
                                                                           [
                                                                              "grupetL" => 1, 
                                                                              "grupetU" => 1, 
                                                                              "emri" => "Shteti dhe qeverisja" 
                                                                           ], 
                                                                           [
                                                                                 "grupetL" => 1, 
                                                                                 "grupetU" => 1, 
                                                                                 "emri" => "Parimet e sistemeve informative" 
                                                                              ], 
                                                                           [
                                                                                    "grupetL" => 1, 
                                                                                    "grupetU" => 1, 
                                                                                    "emri" => "Metodologjia e Kerkimit Shkencor" 
                                                                                 ], 
                                                                           [
                                                                                       "grupetL" => 1, 
                                                                                       "grupetU" => 1, 
                                                                                       "emri" => "Sistemet Virtuale" 
                                                                                    ], 
                                                                           [
                                                                                          "grupetL" => 1, 
                                                                                          "grupetU" => 1, 
                                                                                          "emri" => "Statistike per E-Qeverisje" 
                                                                                       ], 
                                                                           [
                                                                                             "grupetL" => 1, 
                                                                                             "grupetU" => 1, 
                                                                                             "emri" => "Menaxhimi dhe E-Qeverisja" 
                                                                                          ], 
                                                                           [
                                                                                                "grupetL" => 1, 
                                                                                                "grupetU" => 1, 
                                                                                                "emri" => "Kompjuteristike" 
                                                                                             ], 
                                                                           [
                                                                                                   "grupetL" => 1, 
                                                                                                   "grupetU" => 1, 
                                                                                                   "emri" => "Informatika Sociale" 
                                                                                                ], 
                                                                           [
                                                                                                      "grupetL" => 1, 
                                                                                                      "grupetU" => 1, 
                                                                                                      "emri" => "Qeverisja Korporative" 
                                                                                                   ] 
                                                                        ] 
                                                                     ] 
                                                                  ], 
                                                                  "specializimet" => [
                                                                                                      ] 
                                                               ] 
                     ] 
                  ] 
               ] 
            ], 
            [
                                                                                                            "grada" => "Bachelor", 
                                                                                                            "departamentet" => [
                                                                                                               [
                                                                                                                  "departamenti" => "Shkenca Kompjuterike", 
                                                                                                                  "programet" => [
                                                                                                                     [
                                                                                                                        "programi" => "Shkenca Kompjuterike - 2016", 
                                                                                                                        "semestrat" => [
                                                                                                                           [
                                                                                                                              "semestri" => 3, 
                                                                                                                              "lendet" => [
                                                                                                                                 [
                                                                                                                                    "grupetL" => 1, 
                                                                                                                                    "grupetU" => 2, 
                                                                                                                                    "emri" => "ALGORITMET & STRUKTURAT E TË DHËNAVE" 
                                                                                                                                 ], 
                                                                                                                                 [
                                                                                                                                       "grupetL" => 1, 
                                                                                                                                       "grupetU" => 2, 
                                                                                                                                       "emri" => "PROBABILITETI DHE STATISTIKA" 
                                                                                                                                    ], 
                                                                                                                                 [
                                                                                                                                          "grupetL" => 1, 
                                                                                                                                          "grupetU" => 2, 
                                                                                                                                          "emri" => "BAZAT E TË DHENAVE" 
                                                                                                                                       ], 
                                                                                                                                 [
                                                                                                                                             "grupetL" => 1, 
                                                                                                                                             "grupetU" => 2, 
                                                                                                                                             "emri" => "ZHVILLIMI I WEB APLIKACIONEVE" 
                                                                                                                                          ], 
                                                                                                                                 [
                                                                                                                                                "grupetL" => 1, 
                                                                                                                                                "grupetU" => 1, 
                                                                                                                                                "emri" => "HYRJE NE GRAFIKEN KOMPJUTERIKE" 
                                                                                                                                             ], 
                                                                                                                                 [
                                                                                                                                                   "grupetL" => 1, 
                                                                                                                                                   "grupetU" => 1, 
                                                                                                                                                   "emri" => "SIGURIMI I KUALITETIT TË SOFTUERËVE" 
                                                                                                                                                ] 
                                                                                                                              ] 
                                                                                                                           ], 
                                                                                                                           [
                                                                                                                                                      "semestri" => 5, 
                                                                                                                                                      "lendet" => [
                                                                                                                                                         [
                                                                                                                                                            "grupetL" => 1, 
                                                                                                                                                            "grupetU" => 1, 
                                                                                                                                                            "emri" => "TEKNOLOGJIA E KOMUNIKIMIT NË BIZNES" 
                                                                                                                                                         ], 
                                                                                                                                                         [
                                                                                                                                                               "grupetL" => 1, 
                                                                                                                                                               "grupetU" => 1, 
                                                                                                                                                               "emri" => "E-GOVERNANCE" 
                                                                                                                                                            ], 
                                                                                                                                                         [
                                                                                                                                                                  "grupetL" => 1, 
                                                                                                                                                                  "grupetU" => 1, 
                                                                                                                                                                  "emri" => "ANALIZA E INFORMACIONEVE TË BISNESIT" 
                                                                                                                                                               ], 
                                                                                                                                                         [
                                                                                                                                                                     "grupetL" => 1, 
                                                                                                                                                                     "grupetU" => 1, 
                                                                                                                                                                     "emri" => "SIGURIA E INTERNETIT" 
                                                                                                                                                                  ], 
                                                                                                                                                         [
                                                                                                                                                                        "grupetL" => 10, 
                                                                                                                                                                        "grupetU" => 10, 
                                                                                                                                                                        "emri" => "PROJEKT NË IE (PRAKTIKË)" 
                                                                                                                                                                     ], 
                                                                                                                                                         [
                                                                                                                                                                           "grupetL" => 1, 
                                                                                                                                                                           "grupetU" => 1, 
                                                                                                                                                                           "emri" => "INXHINIERI SOFTUERIKE" 
                                                                                                                                                                        ], 
                                                                                                                                                         [
                                                                                                                                                                              "grupetL" => 1, 
                                                                                                                                                                              "grupetU" => 1, 
                                                                                                                                                                              "emri" => "WEB DESIGN I AVANCUAR" 
                                                                                                                                                                           ], 
                                                                                                                                                         [
                                                                                                                                                                                 "grupetL" => 1, 
                                                                                                                                                                                 "grupetU" => 1, 
                                                                                                                                                                                 "emri" => "DATABAZE E AVANCUAR" 
                                                                                                                                                                              ], 
                                                                                                                                                         [
                                                                                                                                                                                    "grupetL" => 1, 
                                                                                                                                                                                    "grupetU" => 1, 
                                                                                                                                                                                    "emri" => "VIZUALIZIMI DHE PROCESIMI I IMAZHEVE" 
                                                                                                                                                                                 ], 
                                                                                                                                                         [
                                                                                                                                                                                       "grupetL" => 20, 
                                                                                                                                                                                       "grupetU" => 20, 
                                                                                                                                                                                       "emri" => "PROJEKT NE SEW (PRAKTIKË)" 
                                                                                                                                                                                    ] 
                                                                                                                                                      ] 
                                                                                                                                                   ] 
                                                                                                                        ], 
                                                                                                                        "specializimet" => [
                                                                                                                                                                                          [
                                                                                                                                                                                             "specializimi" => "SPECIALIZIMI IE", 
                                                                                                                                                                                             "semestrat" => [
                                                                                                                                                                                                [
                                                                                                                                                                                                   "semestri" => 5, 
                                                                                                                                                                                                   "lendet" => [
                                                                                                                                                                                                      [
                                                                                                                                                                                                         "grupetL" => 1, 
                                                                                                                                                                                                         "grupetU" => 1, 
                                                                                                                                                                                                         "emri" => "TEKNOLOGJIA E KOMUNIKIMIT NË BIZNES" 
                                                                                                                                                                                                      ], 
                                                                                                                                                                                                      [
                                                                                                                                                                                                            "grupetL" => 1, 
                                                                                                                                                                                                            "grupetU" => 1, 
                                                                                                                                                                                                            "emri" => "E-GOVERNANCE" 
                                                                                                                                                                                                         ], 
                                                                                                                                                                                                      [
                                                                                                                                                                                                               "grupetL" => 1, 
                                                                                                                                                                                                               "grupetU" => 1, 
                                                                                                                                                                                                               "emri" => "ANALIZA E INFORMACIONEVE TË BISNESIT" 
                                                                                                                                                                                                            ], 
                                                                                                                                                                                                      [
                                                                                                                                                                                                                  "grupetL" => 1, 
                                                                                                                                                                                                                  "grupetU" => 1, 
                                                                                                                                                                                                                  "emri" => "SIGURIA E INTERNETIT" 
                                                                                                                                                                                                               ], 
                                                                                                                                                                                                      [
                                                                                                                                                                                                                     "grupetL" => 10, 
                                                                                                                                                                                                                     "grupetU" => 10, 
                                                                                                                                                                                                                     "emri" => "PROJEKT NË IE (PRAKTIKË)" 
                                                                                                                                                                                                                  ] 
                                                                                                                                                                                                   ] 
                                                                                                                                                                                                ] 
                                                                                                                                                                                             ] 
                                                                                                                                                                                          ], 
                                                                                                                                                                                          [
                                                                                                                                                                                                                        "specializimi" => "SPECIALIZIMI SEW", 
                                                                                                                                                                                                                        "semestrat" => [
                                                                                                                                                                                                                           [
                                                                                                                                                                                                                              "semestri" => 5, 
                                                                                                                                                                                                                              "lendet" => [
                                                                                                                                                                                                                                 [
                                                                                                                                                                                                                                    "grupetL" => 1, 
                                                                                                                                                                                                                                    "grupetU" => 1, 
                                                                                                                                                                                                                                    "emri" => "INXHINIERI SOFTUERIKE" 
                                                                                                                                                                                                                                 ], 
                                                                                                                                                                                                                                 [
                                                                                                                                                                                                                                       "grupetL" => 1, 
                                                                                                                                                                                                                                       "grupetU" => 1, 
                                                                                                                                                                                                                                       "emri" => "WEB DESIGN I AVANCUAR" 
                                                                                                                                                                                                                                    ], 
                                                                                                                                                                                                                                 [
                                                                                                                                                                                                                                          "grupetL" => 1, 
                                                                                                                                                                                                                                          "grupetU" => 1, 
                                                                                                                                                                                                                                          "emri" => "DATABAZE E AVANCUAR" 
                                                                                                                                                                                                                                       ], 
                                                                                                                                                                                                                                 [
                                                                                                                                                                                                                                             "grupetL" => 1, 
                                                                                                                                                                                                                                             "grupetU" => 1, 
                                                                                                                                                                                                                                             "emri" => "VIZUALIZIMI DHE PROCESIMI I IMAZHEVE" 
                                                                                                                                                                                                                                          ], 
                                                                                                                                                                                                                                 [
                                                                                                                                                                                                                                                "grupetL" => 20, 
                                                                                                                                                                                                                                                "grupetU" => 20, 
                                                                                                                                                                                                                                                "emri" => "PROJEKT NE SEW (PRAKTIKË)" 
                                                                                                                                                                                                                                             ] 
                                                                                                                                                                                                                              ] 
                                                                                                                                                                                                                           ] 
                                                                                                                                                                                                                        ] 
                                                                                                                                                                                                                     ] 
                                                                                                                                                                                       ] 
                                                                                                                     ], 
                                                                                                                     [
                                                                                                                                                                                                                                                   "programi" => "Shkenca Kompjuterike - 2021", 
                                                                                                                                                                                                                                                   "semestrat" => [
                                                                                                                                                                                                                                                      [
                                                                                                                                                                                                                                                         "semestri" => 1, 
                                                                                                                                                                                                                                                         "lendet" => [
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                               "grupetL" => 2, 
                                                                                                                                                                                                                                                               "grupetU" => 4, 
                                                                                                                                                                                                                                                               "emri" => "Programimi I" 
                                                                                                                                                                                                                                                            ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                  "grupetL" => 2, 
                                                                                                                                                                                                                                                                  "grupetU" => 4, 
                                                                                                                                                                                                                                                                  "emri" => "Matematika I per Informatike" 
                                                                                                                                                                                                                                                               ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                     "grupetL" => 2, 
                                                                                                                                                                                                                                                                     "grupetU" => 4, 
                                                                                                                                                                                                                                                                     "emri" => "Qarqet digjitale" 
                                                                                                                                                                                                                                                                  ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                        "grupetL" => 2, 
                                                                                                                                                                                                                                                                        "grupetU" => 4, 
                                                                                                                                                                                                                                                                        "emri" => "Arkitektura e Kompjuterve &amp;amp;SO" 
                                                                                                                                                                                                                                                                     ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                           "grupetL" => 2, 
                                                                                                                                                                                                                                                                           "grupetU" => 4, 
                                                                                                                                                                                                                                                                           "emri" => "Shkrim Akademik" 
                                                                                                                                                                                                                                                                        ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                              "grupetL" => 2, 
                                                                                                                                                                                                                                                                              "grupetU" => 4, 
                                                                                                                                                                                                                                                                              "emri" => "Anglishtja per Shkenca Kompjuterike I" 
                                                                                                                                                                                                                                                                           ], 
                                                                                                                                                                                                                                                            [
                                                                                                                                                                                                                                                                                 "grupetL" => 1, 
                                                                                                                                                                                                                                                                                 "grupetU" => 1, 
                                                                                                                                                                                                                                                                                 "emri" => "Gjermanishtja per Shkenca Kompjuterike I" 
                                                                                                                                                                                                                                                                              ] 
                                                                                                                                                                                                                                                         ] 
                                                                                                                                                                                                                                                      ] 
                                                                                                                                                                                                                                                   ], 
                                                                                                                                                                                                                                                   "specializimet" => [
                                                                                                                                                                                                                                                                                 ] 
                                                                                                                                                                                                                                                ], 
                                                                                                                     [
                                                                                                                                                                                                                                                                                       "programi" => "FSHK2 - 2018", 
                                                                                                                                                                                                                                                                                       "semestrat" => [
                                                                                                                                                                                                                                                                                       ], 
                                                                                                                                                                                                                                                                                       "specializimet" => [
                                                                                                                                                                                                                                                                                          ] 
                                                                                                                                                                                                                                                                                    ] 
                                                                                                                  ] 
                                                                                                               ] 
                                                                                                            ] 
                                                                                                         ] 
         ] 
      ] 
]; 
 
 echo json_encode($jayParsedAry);
});
