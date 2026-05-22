const npsettings = (window.wc.wcSettings.getSetting("paymentMethodData",{}) || {})['halkbank'] || {};
const nplabel = window.wp.htmlEntities.decodeEntities( npsettings.title ) || window.wp.i18n.__( 'Pay by card', 'halkbank' );

const NPContent = () => {
	return wp.element.createElement( 'div', {
		dangerouslySetInnerHTML: {
			__html: wp.htmlEntities.decodeEntities( npsettings.description || '' )
		}
	} );
};
const NPBlock_Gateway = {
    name: 'halkbank',
    label: nplabel,
    content: Object( window.wp.element.createElement )( NPContent, null ),
    edit: Object( window.wp.element.createElement )( NPContent, null ),
    canMakePayment: () => true,
    ariaLabel: nplabel,
    supports: {
        features: npsettings.supports,
    },
};

let nphide = false;
if(typeof Halkbank !== 'undefined'){
	if(Halkbank.hidden == "yes" || Halkbank.hidden == 1 || Halkbank.hidden == "true"){
		if(Halkbank.admin != 1){
			nphide = true;
		}
	}
}

if(!nphide){
	window.wc.wcBlocksRegistry.registerPaymentMethod( NPBlock_Gateway );
}