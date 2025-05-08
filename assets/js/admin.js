(function() {
    "use strict";
    
    const { __ } = wp.i18n;
    const restoreButton = document.querySelector("input[name='squeeze_restore_button']")
    const postsFilterForm = document.querySelector("#posts-filter")
    /**
     * Handle restore defaults button click
     */
    restoreButton?.addEventListener("click", (event) => {
        const form = event.target.closest("form");
        const squeeze_setting_restore_defaults = form.querySelector("#squeeze_setting_restore_defaults")
        squeeze_setting_restore_defaults.value = "1"; // triggers 'update_option_squeeze_options' hook
        HTMLFormElement.prototype.submit.call(form) // default method submit() doesn't work when form has input with name="submit"
    })

    postsFilterForm?.addEventListener("submit", (event) => {
        const action = postsFilterForm.querySelector("select[name='action']").value;

        if (action === "squeeze_bulk_delete_backup") {
            event.preventDefault();
            if (confirm("Are you sure you want to delete backups of the selected images?")) {
                postsFilterForm.submit();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        /**
         * Get Tab Key
         */
        const getTabKey = (href) => href.replace('#', '');
    
        /**
         * Hide all tabs
         */
        const hideAllTabs = (tabs) => {
            tabs.forEach((tab) => {
                const href = getTabKey(tab.getAttribute('href'));
                const content = document.getElementById(href);
                if (content) {
                    content.style.display = 'none';
                }
            });
        };
    
        /**
         * Activate Tab
         */
        const activateTab = (tab, tabs) => {
            const href = getTabKey(tab.getAttribute('href'));
            const content = document.getElementById(href);
    
            tabs.forEach((t) => t.classList.remove('nav-tab-active'));
            tab.classList.add('nav-tab-active');
    
            if (content) {
                content.style.display = '';
            }
    
            window.history.pushState({ tab: href }, tab.textContent, `#${href}`);
        };
    
        /**
         * Initialize Tabs
         */
        const initializeTabs = () => {
            const tabs = Array.from(document.querySelectorAll('a.nav-tab'));
            let activeTab = null;
            let firstTab = null;
    
            hideAllTabs(tabs);
    
            tabs.forEach((tab, index) => {
                const href = getTabKey(tab.getAttribute('href'));
                if (!firstTab) firstTab = tab;
                if (tab.classList.contains('nav-tab-active')) activeTab = tab;
    
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    hideAllTabs(tabs);
                    activateTab(tab, tabs);
                });
    
                if (window.location.hash === `#${href}`) {
                    activeTab = tab;
                }
            });
    
            if (!activeTab) {
                activeTab = firstTab;
            }
    
            if (activeTab) {
                activateTab(activeTab, tabs);
            }
        };
    
        /**
         * Handle Back/Forward Navigation
         */
        const handlePopState = () => {
            const hash = window.location.hash;
            const tab = document.querySelector(`a.nav-tab[href="${hash}"]`);
            if (tab) {
                const tabs = Array.from(document.querySelectorAll('a.nav-tab'));
                hideAllTabs(tabs);
                activateTab(tab, tabs);
            }
        };
    
        initializeTabs();
        window.addEventListener('popstate', handlePopState);

        // hide hidden options if no :has support
        if (!CSS.supports('selector(:has(*))')) {
            const hiddenOptions = document.querySelectorAll('.form-table .squeeze-hidden');
            hiddenOptions.forEach((option) => {
                const tr = option.closest('tr');
                tr.style.display = 'none';
            });
        }

        const autoWebpCheckbox = document.querySelector('input[name="squeeze_options[auto_webp]"]');
        autoWebpCheckbox?.addEventListener('change', (event) => {
            const webpReplaceUrlsCheckbox = document.querySelector('input[name="squeeze_options[webp_replace_urls]"]');
            if (event.target.checked) {
                webpReplaceUrlsCheckbox.classList.remove('squeeze-hidden');
                webpReplaceUrlsCheckbox.closest('tr').style.display = '';
            } else {
                webpReplaceUrlsCheckbox.classList.add('squeeze-hidden');
                webpReplaceUrlsCheckbox.closest('tr').style.display = 'none';
            }
        });
    });

    /**
	 * Create a new MediaLibrarySqueezeFilter
     * 
     * @see https://gist.github.com/danielbachhuber/0e4ff7ad82ffc15ceacf
	 */
	const MediaLibrarySqueezeFilter = wp.media.view.AttachmentFilters.extend({
		id: 'media-attachment-squeeze-filter',

		createFilters() {
			this.filters = {
				all: {
					text: __('Squeeze: All images', 'squeeze'),
					props: { 
                        squeeze_filter: 'all'
                    },
					priority: 10,
				},

				nonsqueezed: {
					text: __('Non Squeezed Images', 'squeeze'),
					props: { 
                        squeeze_filter: 'non-squeezed'
                    },
					priority: 20,
				},
			};
		},
	});

	/**
	 * Extend and override wp.media.view.AttachmentsBrowser to include our new filter.
	 */
	const AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
		createToolbar() {
			// Make sure to load the original toolbar
			AttachmentsBrowser.prototype.createToolbar.call(this);
			this.toolbar.set(
				'MediaLibrarySqueezeFilter',
				new MediaLibrarySqueezeFilter({
					controller: this.controller,
					model: this.collection.props,
					priority: -75,
				}).render()
			);
		},
	});

})()