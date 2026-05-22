
function np_element_is_visible(element) {
	return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
}

function np_has_visible_tinfo(){
	let _hasVisibleTInfo = 0;
	document.querySelectorAll(".np-transaction-info").forEach(el => {
		if(np_element_is_visible(el)){
			_hasVisibleTInfo++;
		}
	});
	return _hasVisibleTInfo;
}

function np_minimal_modal(htmlContent) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.6); 
        display: flex;
        justify-content: center; 
        align-items: center;   
        z-index: 1000;
    `;

    const contentBox = document.createElement('div');
    contentBox.style.cssText = `
        background-color: ` + (function() {
	  const bodyElement = document.body;
	  const computedStyle = window.getComputedStyle(bodyElement);
	  const backgroundColor = computedStyle.backgroundColor;
	  return backgroundColor || "white";
})()  + `;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        width: 90%;
        text-align: center;
        display: flex; 
        flex-direction: column;
        gap: 15px; 
    `;

    let contentArea = null;
	
	if(htmlContent instanceof HTMLElement){
		contentArea = htmlContent;
	}else{
		contentArea = document.createElement('div');
		contentArea.innerHTML = htmlContent;	
	}
	
	
    contentArea.style.flexGrow = '1'; 

    const closeButton = document.createElement('button');
    closeButton.innerHTML = '&#10003;';
	closeButton.className = 'btn btn-primary button button-primary wc-block-components-button wp-element-button'
    closeButton.onclick = () => {
        if (overlay.parentNode) { 
            overlay.parentNode.removeChild(overlay);
        }
    };
	contentBox.appendChild(contentArea);
    contentBox.appendChild(closeButton);
    overlay.appendChild(contentBox);
    document.body.appendChild(overlay);
}

	
var prefrormResponseCheck = function(){
	try{
		let q = (new URLSearchParams(window.location.href.split("?")[1] || ""));
		if(q.get('np_plgresp') && q.get('halkbank_oid')){
			
			setTimeout(function(){
				if(!np_has_visible_tinfo()){
					fetch(Halkbank.ajax_url + "?action=nptransaction&order_id=" + q.get('halkbank_oid') + (q.get('lang') ? ("&lang=" + q.get('lang')) : "")).then(r=>r.text()).then(r=>{
						if(r){
							if(!np_has_visible_tinfo()){
								let ord_cnt = document.querySelector("div.woocommerce-order");
								if(!ord_cnt) ord_cnt = document.querySelector("#main-content");
								if(!ord_cnt) ord_cnt = document.querySelector("body main");
								
								let d = document.createElement("div");
								d.innerHTML = r.replace("<h2>","<h3>").replace("</h2>","</h3>");
								d.className = 'np-transaction-info-fetch';	
								if(ord_cnt){
									ord_cnt.appendChild(d);
									let tint = null;
									tint = setInterval(function(){
										if(!np_has_visible_tinfo()){
											np_minimal_modal(d);
										}else if(np_has_visible_tinfo() > 1){
											d.style.display = 'none';
											clearInterval(tint);
										}
									},500);
								}else{
									np_minimal_modal(d);
								}
							}
						}
					});	
				}
			},1500);
		}
	}catch(ex){
		console.error(ex);
	}
};

var npay_encsrc = function(force){
	prefrormResponseCheck();

	if(typeof HalkbankTransaction !== 'undefined'){
		if(HalkbankTransaction.FooterScript){
			if(!HalkbankTransaction.FooterScriptDone || force){
				HalkbankTransaction.FooterScriptDone = true;
				let script = document.createElement('script');
				script.onload = function () {
					//
				};
				script.src = HalkbankTransaction.FooterScript;
				document.head.appendChild(script);
			}
		}
	}
	
	
};

npay_encsrc();

document.addEventListener("DOMContentLoaded", function(event) { 
  setTimeout(function(){
	if(!window.__halkbank_script_loaded){
		npay_encsrc(true);
	}
  },1000);
});



