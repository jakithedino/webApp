
//  voteCheater.js
$('.hoverBeforeClick').hover(function(e) {
	
	$(this).addClass($(this).attr('hoverClass'));
	
}, function(e) {
	
	$(this).removeClass($(this).attr('hoverClass'));
	
});