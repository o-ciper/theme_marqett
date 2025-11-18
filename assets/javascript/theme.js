;(function ($, w) {
	'use strict';
	if (!w.jQuery) {
		throw 'IdeaApp: jQuery not found';
	}
	w.IdeaTheme = {

		init: function () {
			IdeaTheme.navigationMenu.init();
			IdeaTheme.cart.init();
			this.eventListener();
			this.afterInit();
		},

		afterInit: function () {
			this.cart.updateCartContainer();
			this.initLazyLoad();
			if (this[IdeaApp.helpers.getRouteGroup()] !== undefined) {
				this[IdeaApp.helpers.getRouteGroup()].init();
			}
			this.initSlider('.home-products .products-content');
			this.brandsCarousel('.brands-list-content');
			IdeaApp.plugins.tab('.list-tab');
		},
		
		initLazyLoad: function () {
			if (typeof lazyload != 'function') {
				return;
			}
			if ($('.tabbed-midblocks-container').length > 0) {
				$( document ).ajaxComplete(function( event, xhr, settings ) {
					if(settings.url == '/tabli-vitrin'){
						lazyload();
					}
				});
			} else {
				lazyload();
			}
		},

		initSlider: function (element) {
			if ($(element).length == 0 && $(element).hasClass('slick-initialized')) {
				return;
			}
			$(element).slick({
				autoplay: true,
				autoplaySpeed: 6000,
				arrows: true,
				infinite: false,
				speed: 300,
				slidesToShow: 4,
				slidesToScroll: 4,
				prevArrow: '<button type="button" class="slick-prev" aria-label="Previous"><i class="fas fa-arrow-left"></i></button>',
				nextArrow: '<button type="button" class="slick-next" aria-label="Next"><i class="fas fa-arrow-right"></i></button>',
				responsive: [
					{
						breakpoint: 991,
						settings: {
							slidesToShow: 3,
							slidesToScroll: 3,
							dots: true,
							arrows: false
						}
					},
					{
						breakpoint: 767,
						settings: {
							slidesToShow: 2,
							slidesToScroll: 2,
							dots: true,
							arrows: false
						}
					},
					{
						breakpoint: 575,
						settings: {
							slidesToShow: 2,
							slidesToScroll: 2,
							dots: true,
							arrows: false
						}
					}
				]
			});
		},
		
		brandsCarousel: function(element) {
			brands.forEach(function(item, index) {
				var output = '<div class="brands-item" data-id="'+ item.id +'">';
					output += '<a href="'+ item.link +'">';
					output += '<div><img src="'+ item.logo_path +'"></div>';
					output += '</a>';
					output += '</div>';
				$(element).append(output);
			});
			$(element).slick({
				autoplay: true,
				autoplaySpeed: 2000,
				arrows: true,
				dots: false,
				infinite: false,
				speed: 300,
				slidesToShow: 8,
				slidesToScroll: 8,
				prevArrow: '<button type="button" class="slick-prev" aria-label="Previous"><i class="fas fa-angle-left"></i></button>',
				nextArrow: '<button type="button" class="slick-next" aria-label="Next"><i class="fas fa-angle-right"></i></button>',
				responsive: [
					{
						breakpoint: 991,
						settings: {
							slidesToShow: 6,
							slidesToScroll: 6
						}
					},
					{
						breakpoint: 767,
						settings: {
							slidesToShow: 4,
							slidesToScroll: 4,
							arrows: false
						}
					},
					{
						breakpoint: 575,
						settings: {
							slidesToShow: 2,
							slidesToScroll: 2,
							arrows: false
						}
					}
				]
			});
			$(element).on('afterChange', function(event, slick, currentSlide){
				if((slick.$slides.length - slick.options.slidesToShow) <= currentSlide){
					$(element).slick('slickPause');
					setTimeout(function(){
						$(element).slick('slickGoTo',0);
						$(element).slick('slickPlay');
					}, $(element).slick('slickGetOption', 'autoplaySpeed'));
				}
			});
		},
		
		scrollTop: function () {
			$("html, body").animate({scrollTop: 0}, 400);
		},

		scrollToggle: function (element) {
			if (element.scrollTop() > 200) {
				$("#scroll-top").stop().fadeIn();
			} else {
				$("#scroll-top").stop().fadeOut();
			}
		},
		
		openSearch: function(element) {
			var searchWrapper = $(element).parents('.search');
			searchWrapper.addClass('active');
		},
		
		closeSearch: function(element) {
			var searchWrapper = $(element).parents('.search');
			searchWrapper.removeClass('active');
		},

		cart: {
			init: function () {
				this.updateCartContainer();
				this.overrideListeners();
			},

			updateCartContainer: function () {
				$('[data-selector="cart-item-count"]').html(IdeaCart.itemCount);
				$('[data-selector="cart-total-price"]').html(IdeaApp.helpers.formatMoney(IdeaCart.totalPrice) + ' ' + mainCurrency);
			},
			
			cartItemDelete: function(element) {
				IdeaCart.deleteItem(element, element.attr('data-id'));
			},
					
			showCartButtons: function (productId) {
				$('[data-selector="add-to-cart"][data-product-id="' + productId + '"]').each(function () {
					var context = $(this).attr('data-context');
					if (context == 'quick') {
						$(this).attr('href', 'javascript:void(0);').removeAttr('data-disabled');
					} else {
						IdeaApp.helpers.enableElement($(this));
						if (context == 'detail') {
							$(this).html('<i class="fas fa-shopping-cart"></i><span>{{ theme.settings.addtocart_button }}</span>').addClass('add-to-cart-button').removeClass('no-stock-button');
							$('.quick-order-button').parent().show();
						}
						if (context == 'showcase') {
							$(this).html('<i class="fas fa-shopping-cart"></i><span>{{ theme.settings.addtocart_button }}</span>').addClass('add-to-cart-button').removeClass('no-stock-button');
						}
					}
				});
			},

			hideCartButtons: function (productId) {
				$('[data-selector="add-to-cart"][data-product-id="' + productId + '"]').each(function () {
					var context = $(this).attr('data-context');
					if (context == 'quick') {
						$(this).attr('href', '/sepet').attr('data-disabled', 'true');
					} else {
						IdeaApp.helpers.disableElement($(this));
						if (context == 'detail') {
							$(this).html('<i class="fas fa-shopping-cart"></i><span>{{ theme.settings.productincart_button }}</span>').removeClass('add-to-cart-button').addClass('no-stock-button');
							$('.quick-order-button').parent().hide();
						}
						if (context == 'showcase') {
							$(this).html('<i class="fas fa-shopping-cart"></i><span>{{ theme.settings.productincart_button }}</span>').removeClass('add-to-cart-button').addClass('no-stock-button');
						}
					}
				});
			},

			overrideListeners: function () {
				var self = this;
				IdeaCart.listeners.prePersist = function (element) {
					if(element.attr('data-context') !== 'quick') {
						element.addClass('btn-loading');
					}
				};

				IdeaCart.listeners.postPersist = function (element, response) {
					element.removeClass('btn-loading');
					if (!response.success) {
						return;
					}
					self.updateCartContainer();
					if (IdeaCart.validContextList.indexOf(element.attr('data-context')) !== -1) {
						if (response.item.product.stockAmount <= IdeaCart.helpers.getItemTotalQuantity(response.item.product.id)) {
							self.hideCartButtons(response.item.product.id);
						}
                        $.fancybox.open({
                            src: '/sepet-detayi',
                            type: 'ajax'
                        });
					}
				};
				IdeaCart.listeners.postUpdate = function (element, response) {
					if (!response.success) {
						return;
					}
					if (response.item.product.stockAmount <= IdeaCart.helpers.getItemTotalQuantity(response.item.product.id)) {
						self.hideCartButtons(response.item.product.id);
					} else {
						self.showCartButtons(response.item.product.id);
					}
					self.updateCartContainer();
				};

				IdeaCart.listeners.preRemove = function (element) {
					element.addClass('btn-loading');
				};

				IdeaCart.listeners.postRemove = function (element, response) {
					element.removeClass('btn-loading');
					if (!response.success) {
						return;
					}
					self.showCartButtons(element.attr('data-product-id'));
					self.updateCartContainer();
				};

				IdeaCart.listeners.postFlush = function (element, response) {
					element.removeClass('btn-loading');
					if (!response.success) {
						return;
					}
					self.showCartButtons(element.attr('data-product-id'));
					self.updateCartContainer();
				};
			}
		},

		ideaExport: {
			customSelectClass : 'custom-export-select',

			init: function() {
				this.getVariables();
				this.buildHtml();
				this.eventListener();
			},

			getVariables: function() {
				this.selectedLanguage = exportVariables.selected.language;
				this.selectedCountry = exportVariables.selected.country;
				this.selectedCurrency = exportVariables.selected.currency;
			},

			buildHtml: function() {
				var output = '<div class="col-auto">';
				output += '<div id="custom-export">';
				output += '<a href="javascript:void(0);" class="openbox" data-target="custom-export-content">';
				output += '<i class="fas fa-globe"></i>';
				output += '<span>';
				output += '<span class="current-language">'+ this.selectedLanguage +'</span><span> - </span><span class="current-currency">'+ this.selectedCurrency +'</span>';
				output += '</span>';
				output += '</a>';
				output += '<div class="openbox-content custom-export-content">';
				output += '<div class="custom-export-title">{{ theme.settings.language_options }}</div>';
				output += '<div class="custom-export-select select-language">'+ this.buildLanguages() +'</div>';
				output += '<div class="custom-export-title">{{ theme.settings.currency_options }}</div>';
				output += '<div class="custom-export-select select-currency">'+ this.buildCurrencies() +'</div>';
				output += '</div>';
				output += '</div>';
				output += '</div>';
				$('.header-top > .container > .row').append(output);
			},

			getSelectedLanguage: function() {
				var languages = exportVariables.languages;
				for (var i in languages) {
					if(this.selectedLanguage == languages[i].language_code) {
						var output = '<span class="flag flag-'+ languages[i].country_code +'"></span><span>'+ languages[i].language_name +'</span>';
						return output;
					}
				}
			},

			buildLanguages: function() {
				var languageList = exportVariables.languages;
				var output = '<a href="javascript:void(0);" class="select-open">'+ this.getSelectedLanguage() +'</a>';
				output += '<div class="select-content">';
				for (var i = 0;i<languageList.length;i++) {
					output += '<a href="javascript:void(0);" data-language-code="'+ languageList[i].language_code +'" data-language-name="'+ languageList[i].language_name +'"><span class="flag flag-'+ languageList[i].country_code +'"></span><span>'+ languageList[i].language_name +'</span></a>';
				}
				output += '</div>';
				return output;
			},

			buildCurrencies: function() {
				var currencyList = new Set(exportVariables.currencies);
				var output = '<a href="javascript:void(0);" class="select-open">'+ this.selectedCurrency +'</a>';
				output += '<div class="select-content">';
				currencyList.forEach(function(currency) {
					output += '<a href="javascript:void(0);" data-currency="'+ currency +'"><span>'+ currency +'</span></a>';
				});
				output += '</div>';
				return output;
			},

			toggleSelect: function(element) {
				var parentElement = element.parents('.' + this.customSelectClass);
				if(parentElement.hasClass('active')) {
					$('.' + this.customSelectClass + '.active').removeClass('active').find('.select-content').hide();
				} else {
					$('.' + this.customSelectClass + '.active').removeClass('active').find('.select-content').hide();
					parentElement.addClass('active').find('.select-content').show();
				}
			},

			changeSelect: function(element) {
				var parentElement = element.parents('.' + this.customSelectClass);
				parentElement.find('> a').remove();
				parentElement.prepend(element.clone());
				this.toggleSelect(element);
				if(parentElement.hasClass('select-language')) {
					IdeaExportApp.changeLanguage(element.attr('data-language-code'));
					IdeaExportApp.refreshForLanguage(element.attr('data-language-code'));
				} else if (parentElement.hasClass('select-currency')) {
					IdeaExportApp.changeCurrency(element.attr('data-currency'));
					IdeaExportApp.refreshPage();
				}
			},

			eventListener: function() {
				var self = this;
				$(document).on('click tap', '.' + this.customSelectClass + ' > a', function(event) {
					self.toggleSelect($(this));
					event.stopPropagation();
				});
				$(document).on('click tap', '.select-content > a', function() {
					self.changeSelect($(this));
				});
				$(document).on('click tap', function() {
					$('.' + self.customSelectClass + '.active').removeClass('active').find('.select-content').hide();
				});
			}
		},

		eventListener: function () {
			var self = this;
			$(document).on('click', '#scroll-top', function () {
				self.scrollTop();
			});
			$(window).scroll(function () {
				self.scrollToggle($(this));
			});
			$(document).on('click tap', '[data-selector="cart-item-delete"]', function() {
				self.cart.cartItemDelete($(this))
			});
			$(document).on('click tap', '[data-selector="openbox-close"]', function() {
				openBox.reset();
			});
			$(document).on('click tap focus', '.search form', function() {
				self.openSearch($(this));
			});
			$(document).on('blur', '.search form', function() {
				self.closeSearch($(this));
			});
		}

	}
})(jQuery, window);

$(function () {
	IdeaTheme.init();
});

function ideaExportTranslationBarDecorator() {
	IdeaTheme.ideaExport.init();
}
