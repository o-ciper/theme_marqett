;(function ($, w) {
	'use strict';
	if (!w.jQuery) {
		throw 'IdeaApp: jQuery not found';
	}
	w.IdeaTheme.product = {

		init: function () {
			this.thumbImagesCarousel();
			this.zoom.init();
			this.afterInit();
		},

		afterInit: function () {
			IdeaApp.product.productTab('.product-detail-tab', function () {
			}, function () {
				if (IdeaApp.helpers.matchMedia('(max-width: 991px)')) {
					$('body, html').scrollTop($(this).offset().top - 10);
				}
			});
			IdeaTheme.initSlider('.similar-products .products-content');
			IdeaTheme.initSlider('.offered-products .products-content');
			IdeaTheme.initLazyLoad();
		},

		thumbImagesCarousel: function () {
			$('#product-thumb-image').slick({
				vertical: false,
				verticalSwiping: false,
				autoplay: false,
				arrows: true,
				infinite: false,
				speed: 300,
				slidesToShow: 5,
				slidesToScroll: 5,
				prevArrow: '<button type="button" class="slick-prev"><i class="fas fa-arrow-left"></i></button>',
				nextArrow: '<button type="button" class="slick-next"><i class="fas fa-arrow-right"></i></button>',
				responsive: [
					{
						breakpoint: 767,
						settings: {
							vertical: false,
							verticalSwiping: false,
							arrows: false,
							slidesToShow: 4,
							slidesToScroll: 4
						}
					}
				]
			});
		},

		zoom: {
			config: {
				gallery: 'product-thumb-image',
				responsive: true,
				zoomType: "inner",
				borderSize: 0,
				cursor: 'crosshair',
				onZoomedImageLoaded: function () {
					$('#primary-image').unbind('touchmove mousewheel');
					if($('#product-thumb-image .thumb-item a.zoomGalleryActive').length < 1) {
						$('#product-thumb-image .thumb-item:first-child a').addClass('zoomGalleryActive');
					}
				}
			},
			init: function() {
				$('.zoomContainer').remove();
				$('#primary-image').elevateZoom(this.config);
				this.eventListener();
			},
			eventListener: function() {
				$('#primary-image').on('click tap', function () {
					$.fancybox.open($(this).data('elevateZoom').getGalleryList(), {
						i18n: {
							en: {
								SHARE: "{{ theme.settings.share_product }}"
							}
						}
					});
					return false;
				});
				$('#product-thumb-image .thumb-item a').on('click tap', function() {
					var image = $('#primary-image');
					$('.zoomContainer').remove();
					image.removeData('elevateZoom').attr('src', $(this).data('image')).data('zoom-image', $(this).data('zoom-image')).elevateZoom(IdeaTheme.product.zoom.config);
				});
			}
		}
	}
})(jQuery, window);