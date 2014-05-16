jQuery( document ).ready( function ( e ) {
	jQuery('.sensei-view-all-participants a').click( function( event ) {
		event.preventDefault();
		var hiddenLearners = '.sensei-course-participant.hide';
		var txt = jQuery( hiddenLearners ).is(':visible') ? 'View All' : 'Close';
     	jQuery(this).text(txt);
		jQuery( hiddenLearners ).slideToggle( 300 );
	});
});