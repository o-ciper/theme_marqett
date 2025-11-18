;(function ($, w) {
	'use strict';
	if (!w.jQuery) {
		throw 'IdeaTheme: jQuery not found';
	}
	w.IdeaTheme.navigationMenu = {

		activeClass: 'active',
		bodyActiveClass: 'navigation-active',

		init: function () {
			if ($('#navigation').length == 0) {
				return;
			}
			this.mobile.init();
			this.controlMedia();
			this.createOverlay();
			this.eventListener();
		},

		mobile: {
			activeClass: 'active',
			menuRendered: false,
			mobileMenuId: 'mobile-navigation',

			init: function () {
				this.eventListener();
			},

			buildMenu: function () {
				var self = this;
				if (self.menuRendered) {
					return;
				}
				$('.header-middle-inside > .row').prepend('<div class="col-auto d-block d-lg-none"><div class="toggle-bar" data-selector="toggle-bar"><i class="fas fa-bars"></i></div></div>');
				$('body').append('<div id="' + self.mobileMenuId + '"><div class="'+ self.mobileMenuId +'"></div></div>');
				if (navigationMenu !== null) {
					$('.' + this.mobileMenuId).append(self.createCategoriesHtml(navigationMenu.categories, null, 1))
				}
				if (typeof menuItems['row2'] !== "undefined") {
					$('.' + this.mobileMenuId).append(self.createMenuItemsHtml(menuItems['row2']));
				}
				if (typeof menuItems['row1'] !== "undefined") {
					$('.' + this.mobileMenuId).append(self.createMenuItemsHtml(menuItems['row1']));
				}
				this.menuRendered = true;
			},

			createMenuItemsHtml: function (menuItems) {
				var output = '<div class="mobile-navigation-menu-items"><ul>';
				$.each(menuItems, function (i, item) {
					output += '<li><a href="' + item.link + '" target="' + item.target + '"><div><span>' + item.label + '</span></div></a></li>';
				});
				output += '</ul></div>';
				return output;
			},

			createCategoriesHtml: function (categories, parentCategory, level) {
				var self = this;
				var output = '<div class="category-level-' + level + '">';
				if (level > 1) {
					output += '<div class="mobile-navigation-back"><a href="javascript:void(0);"><i class="fas fa-chevron-left"></i><span>{{ theme.settings.mobile_categories_goback }}</span></a></div>';
					output += '<div class="mobile-navigation-parent"><a href="' + parentCategory.url + '">' + parentCategory.name + '</a></div>';
				}
				output += '<ul>';
				$.each(categories, function (i, item) {
					var imageContent = '';

					if (navigationMenu.settings.useCategoryImage) {
						imageContent = '<div><img src="' + item.imageUrl + '" alt="' + item.name + '"/></div>';
					}
					if (item.subCategories.length > 0) {
						output += '<li class="has-sub-category"><a href="javascript:void(0);"><div>' + imageContent + '<span>' + item.name + '</span></div><i class="fas fa-chevron-right"></i></a>' + self.createCategoriesHtml(item.subCategories, item, (level + 1)) + '</li>';
					} else {
						output += '<li><a href="' + item.url + '"><div>' + imageContent + '<span>' + item.name + '</span></div></a></li>';
					}
				});
				output += '</ul></div>';
				return output;
			},

			openSubCategories: function (element) {
				if (element.hasClass(this.activeClass)) {
					element.removeClass(this.activeClass);
				} else {
					var subCategoryHeight = element.find('> div').outerHeight();
					$('#' + this.mobileMenuId).scrollTop(0);
					$('.' + this.mobileMenuId).css('height',subCategoryHeight);
					element.addClass(this.activeClass);
				}
			},

			closeSubCategories: function (element) {
				element.parent('.has-sub-category').removeClass(this.activeClass);
				if(element.hasClass('category-level-2')) {
					$('.' + this.mobileMenuId).css('height','auto');
				}
				if(element.hasClass('category-level-3')) {
					var subCategoryHeight = element.parents('.category-level-2').outerHeight();
					$('.' + this.mobileMenuId).css('height',subCategoryHeight);
				}
			},

			toggleNavigation: function () {
				if ($('body').hasClass(IdeaTheme.navigationMenu.bodyActiveClass)) {
					$('body').removeClass(IdeaTheme.navigationMenu.bodyActiveClass);
				} else {
					$('body').addClass(IdeaTheme.navigationMenu.bodyActiveClass);
				}
			},

			eventListener: function () {
				var self = this;
				$(document).on('click', '[data-selector="toggle-bar"]', function (e) {
					e.stopPropagation();
					self.toggleNavigation();
				});

				$(document).on('click', '#' + self.mobileMenuId, function (e) {
					e.stopPropagation();
				});

				$(document).on('click', '#' + self.mobileMenuId + ' .has-sub-category a', function () {
					self.openSubCategories($(this).parent());
				});

				$(document).on('click', '.mobile-navigation-back', function () {
					self.closeSubCategories($(this).parent());
				});
			}

		},

		createOverlay: function () {
			$('body').append('<div class="navigation-menu-overlay" />');
		},
		
		overflowControl: function(element) {
			var containerOffset = $('#navigation').outerWidth() + $('#navigation').offset().left;
			var elementOffset = element.find('> div').offset().left + element.find('> div').outerWidth()
			if(elementOffset > containerOffset) {
				element.find('> div').css({
					marginLeft: '-' + (elementOffset - containerOffset) + 'px'
				});
			}
		},

		openDropMenu: function (element) {
			if(element.hasClass('has-sub-category')) {
				$('body').addClass(this.bodyActiveClass);				
			}
			element.addClass(this.activeClass).siblings().removeClass(this.activeClass);
		},

		closeDropMenu: function (element) {
			element.removeClass(this.activeClass);
			$('body').removeClass(this.bodyActiveClass);
		},

		controlMedia: function () {
			if (IdeaApp.helpers.matchMedia('(max-width: 991px)')) {
				this.mobile.buildMenu();
			}
		},

		eventListener: function () {
			var self = this;
			$(document).on('click', function () {
				if ($('body').hasClass(self.bodyActiveClass)) {
					$('body').removeClass(self.bodyActiveClass);
				}
			});
			
			$(document).on('mouseenter', '[data-selector="first-level-navigation"]', function() {
				self.openDropMenu($(this));
			});
			
			$(document).on('mouseleave', '[data-selector="first-level-navigation"]', function() {
				self.closeDropMenu($(this));
			});

			$(window).on('resize', function () {
				self.controlMedia();
			});

		}
	}
})(jQuery, window);