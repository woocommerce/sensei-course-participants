jQuery( document ).ready( function () {
	jQuery('.sensei-view-all-participants a').click( function( event ) {
		event.preventDefault();
		var el = jQuery(this);
		var hiddenLearners = el.closest('.widget_sensei_course_participants').find('.sensei-course-participant.hide');
		var txt = hiddenLearners.is(':visible') ? sensei_course_participants_frontend.view_all : sensei_course_participants_frontend.close;
		jQuery(this).text(txt);
		hiddenLearners.slideToggle( 300 );
	});
});
