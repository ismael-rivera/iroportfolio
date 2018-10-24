
 //  ______     _ _                        
 // |  ____|   | | |                       
 // | |__ _   _| | |_ __   __ _  __ _  ___ 
 // |  __| | | | | | '_ \ / _` |/ _` |/ _ \
 // | |  | |_| | | | |_) | (_| | (_| |  __/
 // |_|   \__,_|_|_| .__/ \__,_|\__, |\___|
 //                | |           __/ |     
 //                |_|          |___/      



var myFullpage = new fullpage('#fullpage', {
	//Navigation
	menu: '#menu',
	lockAnchors: true,
	anchors:['sect1', 'sect2', 'sect3', 'sect4', 'sect5', 'sect6', 'sect7', 'sect8'],
	navigation: true,
	navigationPosition: 'right',
	showActiveTooltip: true,
	slidesNavigation: true,
	slidesNavPosition: 'bottom',

	//Scrolling
	css3: true,
	scrollingSpeed: 700,
	autoScrolling: true,
	fitToSection: true,
	fitToSectionDelay: 1000,
	scrollBar: false,
	easing: 'easeInOutCubic',
	easingcss3: 'ease',
	loopBottom: false,
	loopTop: false,
	loopHorizontal: true,
	continuousVertical: false,
	continuousHorizontal: false,
	scrollHorizontally: false,
	interlockedSlides: false,
	dragAndMove: false,
	offsetSections: false,
	resetSliders: false,
	fadingEffect: false,
	//normalScrollElements: '#element1, .element2',
	scrollOverflow: false,
	scrollOverflowReset: false,
	scrollOverflowOptions: null,
	touchSensitivity: 15,
	normalScrollElementTouchThreshold: 5,
	bigSectionsDestination: null,

	//Accessibility
	keyboardScrolling: true,
	animateAnchor: true,
	recordHistory: true,

	//Design
	controlArrows: true,
	verticalCentered: true,
	sectionsColor : ['#343a40', '#343a40', '#dfdfdf', '#dfdfdf', '#111E6C', '#111E6C', '#FFD300', ],
	fixedElements: '#header, .footer',
	responsiveWidth: 0,
	responsiveHeight: 0,
	responsiveSlides: false,
	parallax: false,
	parallaxOptions: {type: 'reveal', percentage: 62, property: 'translate'},
	paddingTop: '7em',
	paddingBottom: '7em',

	//Custom selectors
	sectionSelector: '.section',
	slideSelector: '.slide',

	lazyLoading: true,


	//Other
	licenseKey: 'OPEN-SOURCE-GPLV3-LICENSE',

});







 //
 //   _____                _ _                       _      
 //  / ____|              | | |                     (_)     
 // | (___   ___ _ __ ___ | | |_ __ ___   __ _  __ _ _  ___ 
 //  \___ \ / __| '__/ _ \| | | '_ ` _ \ / _` |/ _` | |/ __|
 //  ____) | (__| | | (_) | | | | | | | | (_| | (_| | | (__ 
 // |_____/ \___|_|  \___/|_|_|_| |_| |_|\__,_|\__, |_|\___|
 //                                             __/ |       
 //                                            |___/        


	// // init controller
	// var controller = new ScrollMagic.Controller();

	// // build tween
	// var tween = TweenMax.from("#animate", 0.5, {autoAlpha: 0, scale: 0.7});

	// // build scene
	// var scene = new ScrollMagic.Scene({triggerElement: "a#top", duration: 200, triggerHook: "onLeave"})
	// 				.setTween(tween)
	// 				.addIndicators() // add indicators (requires plugin)
	// 				.addTo(controller);

	// // change behaviour of controller to animate scroll instead of jump
	// controller.scrollTo(function (newpos) {
	// 	TweenMax.to(window, 0.5, {scrollTo: {y: newpos}});
	// });

	// //  bind scroll to anchor links
	// $(document).on("click", "a[href^='#']", function (e) {
	// 	var id = $(this).attr("href");
	// 	if ($(id).length > 0) {
	// 		e.preventDefault();

	// 		// trigger scroll
	// 		controller.scrollTo(id);

	// 			// if supported by the browser we can even update the URL.
	// 		if (window.history && window.history.pushState) {
	// 			history.pushState("", document.title, id);
	// 		}
	// 	}
	// });