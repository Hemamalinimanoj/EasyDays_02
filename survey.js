var ICON_PRE='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
var ICONS=["<rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\"/><path d=\"M16 2v4M8 2v4M3 10h18\"/>","<path d=\"M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z\"/>","<path d=\"M12 2.7l5.7 5.6a8 8 0 1 1-11.4 0z\"/>","<path d=\"M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z\"/><path d=\"M3 6h18\"/><path d=\"M16 10a4 4 0 0 1-8 0\"/>","<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M12 6v6l4 2\"/>","<path d=\"M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.9L12 17.8 5.8 21l1.2-6.9-5-4.9 6.9-1z\"/>"];
var BULB='<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/>', ARL='<path d="M19 12H5M12 19l-7-7 7-7"/>', ARR='<path d="M5 12h14M12 5l7 7-7 7"/>';

var YN = ["Yes","No"];
var IMP = ["Highly important","Important","Not important","Irrelevant"];

var S = [
 {t:"Calendar Management", q:[
  {id:"q1",k:"mc",r:1,t:"How important is it to remember your period dates every month?",o:IMP},
  {id:"q2",k:"mc",r:1,t:"Why is this important?",o:["To easily manage those dates","To carry the essentials","To take leave from work, college or school","To plan any auspicious rituals at home"]},
  {id:"q3",k:"mc",r:1,t:"How do you remember your period dates?",o:["By noting it in a calendar","By memory","Through my mother or sister","By physically noting it in a diary"]},
  {id:"q4",k:"mc",r:1,t:"If there were a digital calendar to record and remind you of your dates, with high privacy, and to track the biological changes in your body, would you use it?",o:YN}]},
 {t:"Interactive Private Chat or Q&A with a Renowned Gynaecologist", q:[
  {id:"q7",k:"mc",r:1,t:"How important is it to maintain your gynaecological health?",o:IMP},
  {id:"q8",k:"mc",r:1,t:"What do you do when you notice small symptoms?",o:["Visit a gynaecologist immediately","Allow it to cure on its own","Not necessary to visit a doctor","Take guidance from my mother or sister"]},
  {id:"q9",k:"mc",r:1,t:"If there were a digital connect with a renowned gynaecologist, would you be interested in using that platform to chat with the doctor online for basic consultation?",o:YN}]},
 {t:"Detailed Information on Menstruation", q:[
  {id:"q12",k:"mc",r:1,t:"Are you fully aware about menstruation?",o:["Fully aware","Somewhat aware","Knows everything","Not aware in detail"]},
  {id:"q13",k:"mc",r:1,t:"Do you know the importance of menstruation in your body?",o:YN},
  {id:"q14",k:"mc",r:1,t:"Do you want to know more about menstruation and its impact on your body?",o:YN},
  {id:"q15",k:"mc",r:1,t:"Do you want to protect yourself by understanding menstruation?",o:YN}]},
 {t:"How to Choose the Best Napkin for You", q:[
  {id:"q16",k:"mc",r:1,t:"Do you know the importance of using a quality napkin?",o:YN},
  {id:"q17",k:"mc",r:1,t:"Do you know how to choose a quality napkin from the market?",o:YN},
  {id:"q18",k:"mc",r:1,t:"Would you be interested to know how to choose the right quality napkin for you?",o:["Yes","No","Already knows"]}]},
 {t:"Menopause Management", q:[
  {id:"q19",k:"mc",r:1,t:"Do you know about the age of menopause and its impact on your body?",o:YN},
  {id:"q20",k:"mc",r:1,t:"Would you be interested to know about menopause management?",o:YN}]},
 {t:"Overall", q:[
  {id:"q21",k:"mc",r:1,t:"Would you be interested in using a digital platform which gives you calendar management, private room Q&A with a gynaecologist, details about menstruation, the right method to choose a napkin, and menopause management under one roof?",o:YN},
  {id:"q23",k:"mc",r:1,t:"Would you be interested to join us to share your valuable feedback then and there?",n:"This will support us to give this society a complete guide to manage womanhood.",o:["Yes, sure","No, I don’t want to"]}]}
];


var FLAT=[]; S.forEach(function(s,si){s.q.forEach(function(q){FLAT.push({q:q,si:si})})});
var A={}, cur=0, order=[];
var $=function(id){return document.getElementById(id)};
var esc=function(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/"/g,"&quot;")};
function svg(p){return ICON_PRE+p+'</svg>'}

// questions that apply right now (the "If No, why?" ones only when needed)
function visible(){
  return FLAT.filter(function(f){var q=f.q; return !q.show || q.show[1].indexOf(A[q.show[0]]||"")>=0});
}
function updateProgress(){
  order=visible();
  if(cur>order.length-1) cur=order.length-1;
  var n=order.length, pct=Math.round((cur+1)/n*100);
  $("stepno").textContent="Step "+(cur+1)+" of "+n;
  $("fill").style.width=pct+"%";
  $("pct").textContent=pct+"%";
}
function render(){
  updateProgress();
  var f=order[cur], q=f.q, sec=S[f.si], last=cur===order.length-1, cv=A[q.id]||"";
  var hint=q.k==="mc"?(q.r?"Please select one option":"Please select one option (optional)"):(q.r?"Please type your answer":"Your answer (optional)");
  var h='<div class="qrow"><div class="qicon">'+svg(ICONS[f.si])+'</div><div class="qbody">'
   +'<div class="cat">'+esc(sec.t)+'</div>'
   +'<h2 class="qt">'+(cur+1)+'. '+esc(q.t)+'</h2>'
   +'<div class="hintrow"><p class="hint">'+hint+'</p>'+(q.k==="mc"&&!q.r?'<button type="button" class="clr'+(cv?' show':'')+'" id="clr">Clear selection</button>':'')+'</div>';
  if(q.k==="mc"){
    h+='<div class="opts" role="radiogroup">';
    q.o.forEach(function(o){
      h+='<label class="opt"><input type="radio" name="a" value="'+esc(o)+'"'+(cv===o?' checked':'')+'><span class="dot"></span><span class="olab">'+esc(o)+'</span></label>';
    });
    h+='</div>';
  } else if(q.k==="short"){
    h+='<input class="txt" type="text" id="ans" placeholder="Type your answer here" value="'+esc(cv)+'" autocomplete="off">';
  } else {
    h+='<textarea class="txt" id="ans" placeholder="Type your answer here">'+esc(cv)+'</textarea>';
  }
  h+='<p class="msg" id="msg"></p>'
   +(q.n?'<div class="know"><div class="kicon">'+svg(BULB)+'</div><div><b>Good to know</b><span>'+esc(q.n)+'</span></div></div>':'')
   +'</div></div>'
   +'<div class="actions"><button type="button" class="prev" id="prev"'+(cur?'':' style="visibility:hidden"')+'>'+svg(ARL)+'<span>Previous</span></button>'
   +'<button type="button" class="next" id="next"><span>'+(last?"Submit":"Next")+'</span>'+svg(ARR)+'</button></div>';
  var st=$("stage"); st.innerHTML=h; st.classList.remove("in"); void st.offsetWidth; st.classList.add("in");

  st.querySelectorAll("input[type=radio]").forEach(function(r){
    r.onchange=function(){A[q.id]=r.value;$("msg").textContent="";st.classList.remove("shake");updateProgress();
      var c=$("clr"); if(c) c.classList.add("show"); relabel();};
  });
  var ans=$("ans");
  if(ans){
    ans.oninput=function(){A[q.id]=ans.value.trim();$("msg").textContent="";};
    if(q.k==="short") ans.onkeydown=function(e){if(e.key==="Enter"){e.preventDefault();next()}};
    if(window.innerWidth>700) ans.focus();
  }
  var clr=$("clr");
  if(clr) clr.onclick=function(){delete A[q.id];st.querySelectorAll("input[type=radio]").forEach(function(r){r.checked=false});clr.classList.remove("show");updateProgress();relabel()};
  $("prev").onclick=function(){if(cur>0){cur--;render();top_()}};
  $("next").onclick=next;
}
function relabel(){ // "Next" becomes "Submit" on the last visible step
  var b=$("next"); if(b) b.firstChild.textContent=(cur===order.length-1)?"Submit":"Next";
}
function top_(){var p=document.querySelector(".progress");if(p)window.scrollTo({top:Math.max(0,p.offsetTop-20),behavior:"smooth"})}
function next(){
  var f=order[cur], q=f.q;
  if(q.r && !A[q.id]){
    $("msg").textContent=q.k==="mc"?"Please choose an option to continue.":"Please type your answer to continue.";
    var st=$("stage"); st.classList.remove("shake"); void st.offsetWidth; st.classList.add("shake");
    return;
  }
  if(cur<order.length-1){cur++;render();top_();return}
  submit();
}
function submit(){
  var btn=$("next"), err=$("subErr");
  var vis={}; order.forEach(function(f){vis[f.q.id]=1});
  var out={}; FLAT.forEach(function(f){out[f.q.id]=vis[f.q.id]?(A[f.q.id]||""):""});
  btn.disabled=true; btn.firstChild.textContent="Submitting…"; err.classList.add("hide");
  fetch("save.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({answers:out})})
    .then(function(r){
      return r.text().then(function(body){
        var j;
        try { j=JSON.parse(body); }
        catch(e) { throw new Error("The save endpoint did not return JSON. Open this project through Apache (not VS Code Live Server). In XAMPP, use http://localhost/easydays_survey/."); }
        if(!r.ok || !(j&&j.ok)) throw new Error(j&&j.error?j.error:"Could not save your response.");
        return j;
      });
    })
    .then(function(j){
      $("survey").classList.add("hide"); $("done").classList.remove("hide");
      window.scrollTo({top:0,behavior:"smooth"});
      setTimeout(openJoin,900);
    })
    .catch(function(e){
      btn.disabled=false; btn.firstChild.textContent="Submit";
      err.textContent=location.protocol==="file:"
        ? "Please open this page through Apache (http://localhost/easydays_survey/), not by double-clicking the file."
        : (e&&e.message&&e.message!=="Failed to fetch"
          ? e.message
          : "Sorry, we couldn’t reach the server. Check that Apache and MySQL are running, then press Submit again.");
      err.classList.remove("hide");
    });
}

/* ---- "Join us" popup (shown after the survey is submitted) ---- */
var jm=$("joinModal");
function jmShow(id){["jmAsk","jmForm","jmThanks"].forEach(function(x){$(x).classList.toggle("hide",x!==id)})}
function openJoin(){jmShow("jmAsk");jm.classList.remove("hide");document.body.style.overflow="hidden"}
function closeJoin(){jm.classList.add("hide");document.body.style.overflow=""}
$("jmClose").onclick=$("jmDone").onclick=closeJoin;
jm.addEventListener("mousedown",function(e){if(e.target===jm)closeJoin()});
document.addEventListener("keydown",function(e){if(e.key==="Escape"&&!jm.classList.contains("hide"))closeJoin()});
$("jmNo").onclick=function(){
  $("jmThanksT").textContent="Thank you for your valuable time.";
  $("jmThanksP").textContent="We really appreciate your support. 💜";
  jmShow("jmThanks");
};
$("jmYes").onclick=function(){jmShow("jmForm");$("jmName").focus()};
$("jmSend").onclick=function(){
  var n=$("jmName").value.trim(), em=$("jmEmail").value.trim(), ph=$("jmPhone").value.trim(), m=$("jmMsg"), b=$("jmSend");
  if(n.length<2){m.textContent="Please enter your name.";return}
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em)){m.textContent="Please enter a valid email address.";return}
  var digits=ph.replace(/\D/g,"");
  if(digits.length<7||digits.length>15||!/^[0-9+()\-\s]+$/.test(ph)){m.textContent="Please enter a valid phone number.";return}
  m.textContent=""; b.disabled=true; b.firstChild.textContent="Submitting…";
  fetch("join.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({name:n,email:em,phone:ph})})
    .then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok||!j.ok)throw new Error(j.error||"Could not save your details.");})})
    .then(function(){
      $("jmThanksT").textContent="Thank you for joining us!";
      $("jmThanksP").textContent="We’ll contact you soon. 💜";
      jmShow("jmThanks");
    })
    .catch(function(e){m.textContent=e.message&&e.message!=="Failed to fetch"?e.message:"Sorry, we couldn’t reach the server. Please try again.";})
    .then(function(){b.disabled=false;b.firstChild.textContent="Submit"});
};
$("again").onclick=function(e){e.preventDefault();location.reload()};
render();
