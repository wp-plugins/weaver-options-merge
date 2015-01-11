var womMergeJS = {
	doReady: function($) {
		$('#'+womMergeVars.mainAccordion).accordion(
			{collapsible: true,
			 heightStyle:'content',
			 active:parseInt(womMergeVars.activeSection),
			 icons:{'header': 'ui-icon-plus', 'activeHeader': 'ui-icon-minus'}
			});
		$.each(womMergeVars.accnames, function(i,name) {
			$(name).accordion(
				{header:'h4',
				 heightStyle:'content',
				 active: $(name+' input:checked').parents('div').index(name+' div')
				});
		});
		$('#'+womMergeVars.tableName+' input').click(womMergeJS.changeRadioClass);
		$("."+womMergeVars.showButtons).click(womMergeJS.checkShowButton);
		$("#"+womMergeVars.mergeButton).click(womMergeJS.checkMergeButton);
//		$('#'+womMergeVars.mergeForm).scrollTop(50);
		womMergeJS.makeHelper(1);
		womMergeJS.makeHelper(3);
		//need to add text input for user choice
	},
	changeRadioClass: function() {
		var radioname = jQuery(this).attr('name');
		jQuery('#'+womMergeVars.tableName+' input[name="'+radioname+'"]')
			.parents('td').removeClass(womMergeVars.choiceClass);
		jQuery(this).parents('td').addClass(womMergeVars.choiceClass);
	},
	checkShowButton: function(e) {
		var radioname = jQuery(this).data(womMergeVars.showRadio);
		if ( 0 == jQuery('input[name="'+radioname+'_0"]:checked').length ||
					(jQuery('input[name="'+radioname+'_0"]:checked').val() ==
					jQuery('input[name="'+radioname+'_1"]:checked').val() &&
					'+womfile+' != jQuery('input[name="'+radioname+'_0"]:checked').val() ) )	{
			e.preventDefault();
			alert(womMergeMsgs.needtwo);
		}
	},
	checkMergeButton: function(e) {
		var many = jQuery('#'+womMergeVars.mergeForm+' input:checked').length;
		var many2 = jQuery('#'+womMergeVars.mergeForm+' input:checked')
			.filter(function(i){return jQuery(this).val() == '2'}).length;
		var many3 = jQuery('#'+womMergeVars.mergeForm+' input:checked')
			.filter(function(i){return jQuery(this).val() == '3'}).length;
		if ( (0 == many2 && 0 == many3) || (many2 == many) ) {
			e.preventDefault();
			alert(womMergeMsgs.nothing);
		}
	},
	makeButton: function(thelabel, lookfor, side ) {
		var b =	document.createElement('input');
		b.setAttribute('type', 'button');
		b.setAttribute('class', 'button wom-button');
		b.setAttribute('data-lookfor', lookfor);
		b.setAttribute('data-side', side);
		b.value = thelabel;
		if ( 0 == jQuery('#'+womMergeVars.mergeForm+' input[name*="'+lookfor+'"]').length )
			b.style.display = 'none';
		return b;
	},
	makeHelper: function(side) {
		var	temp, holder = document.createElement('div');
		holder.setAttribute('class', 'wom-holder '+(1==side ? 'womleft' : 'womright'));
		holder.appendChild(womMergeJS.makeButton(womMergeMsgs.allselbutton, womMergeVars.mergeChoice, side ));
		holder.appendChild(womMergeJS.makeButton(womMergeMsgs.colorselbutton, 'color]', side ));
		holder.appendChild(womMergeJS.makeButton(womMergeMsgs.clrcssselbutton, 'color_css', side ));
		holder.appendChild(womMergeJS.makeButton('hover', 'hover', side ));
		holder.appendChild(womMergeJS.makeButton(womMergeMsgs.insselbutton, 'insert', side ));
		holder.appendChild(womMergeJS.makeButton('round', 'round', side ));
		holder.appendChild(womMergeJS.makeButton(womMergeMsgs.fontselbutton, 'font', side ));
		temp = womMergeJS.makeButton(womMergeMsgs.siteselbutton, '[_', side );
		holder.appendChild(temp);
		jQuery('#'+womMergeVars.mergeForm).before(holder);
		jQuery(holder).on('click', '.wom-button', function(){
			womMergeJS.chooseThose(jQuery(this).data('lookfor'), jQuery(this).data('side'));
		});
		jQuery(temp).on('click', function(){
			jQuery('#'+womMergeVars.mergeForm+' tr.'+womMergeVars.siteOption+' td:nth-child('+side+') input').click();
		});
	},
	chooseThose: function(lookfor, side) {
		side = Math.min(3, Math.max(1, parseInt(side)||1));
		lookfor = lookfor.toLowerCase().trim().replace(/[^\][!a-z0-9_-]/g, '');		 //sanitize
		if ( '!' == lookfor[0] )
			lookfor = '!="'+lookfor.substr(1);
		else lookfor = '*="'+lookfor;
		jQuery('#'+womMergeVars.mergeForm+' td:nth-child('+side+') input[name'+lookfor+'"]').click();
	}
};
jQuery(document).ready(womMergeJS.doReady);
