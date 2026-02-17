(function($) {
    'use strict';

    function initAdminBarReplacement() {
        if (!document.body.classList.contains('logged-in')) {
            return;
        }
        if (typeof gbp_admin_bar_params === 'undefined') {
            return;
        }

        var adminIcon = $('<div class="gbp-admin-icon"></div>');
        var dropdown = $('<div class="gbp-admin-dropdown"></div>');

        var dashboardUrl = gbp_admin_bar_params.admin_url || '/wp-admin/';
        var themeName = gbp_admin_bar_params.theme_name;
        var postId = gbp_admin_bar_params.post_id || '';
        var postType = gbp_admin_bar_params.post_type || '';
        var isFrontPage = gbp_admin_bar_params.is_front_page || false;
        var isHomePage = gbp_admin_bar_params.is_home_page || false;
        var isArchive = gbp_admin_bar_params.is_archive || false;
        var isCategory = gbp_admin_bar_params.is_category || false;
        var isTag = gbp_admin_bar_params.is_tag || false;
        var isAuthor = gbp_admin_bar_params.is_author || false;
        var isDate = gbp_admin_bar_params.is_date || false;
        var isSearch = gbp_admin_bar_params.is_search || false;
        var is404 = gbp_admin_bar_params.is_404 || false;
        var termId = gbp_admin_bar_params.term_id || '';
        var termTaxonomy = gbp_admin_bar_params.term_taxonomy || '';
        var authorId = gbp_admin_bar_params.author_id || '';

        var editPageLink = '';
        var editSiteLink = '';
        var editHeaderLink = '';
        var editFooterLink = '';
        var dashboardLink = '<a href="' + dashboardUrl + '" class="gbp-dashboard">Dashboard</a>';

        if (!themeName) {
            editSiteLink = '<a href="' + dashboardUrl + 'site-editor.php" class="gbp-edit-site">Website bearbeiten</a>';
            editPageLink = buildEditPageLink(dashboardUrl, gbp_admin_bar_params, postId, postType, isFrontPage, isHomePage, isCategory, isTag, isAuthor, isArchive, isSearch, is404, termId, authorId);
        } else {
            editPageLink = buildEditPageLink(dashboardUrl, gbp_admin_bar_params, postId, postType, isFrontPage, isHomePage, isCategory, isTag, isAuthor, isArchive, isSearch, is404, termId, authorId);
            editSiteLink = buildEditSiteLink(dashboardUrl, themeName, postId, postType, isFrontPage, isHomePage, isCategory, isTag, isAuthor, isArchive, isSearch, is404);
            editHeaderLink = '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template_part%2F' + themeName + '%2F%2Fheader&canvas=edit" class="gbp-edit-header">Header bearbeiten</a>';
            editFooterLink = '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template_part%2F' + themeName + '%2F%2Ffooter&canvas=edit" class="gbp-edit-footer">Footer bearbeiten</a>';
        }

        dropdown.append(editPageLink);
        dropdown.append(editSiteLink);
        dropdown.append(editHeaderLink);
        dropdown.append(editFooterLink);
        dropdown.append(dashboardLink);
        adminIcon.append(dropdown);
        $('body').append(adminIcon);

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.gbp-admin-icon').length) {
                $('.gbp-admin-dropdown').removeClass('active');
            }
        });

        if ('ontouchstart' in window) {
            adminIcon.on('touchstart', function(e) {
                e.preventDefault();
                dropdown.toggleClass('active');
            });
        }
    }

    function getPostTypeLabel(postType) {
        var labels = {
            'post': 'Beitrag',
            'page': 'Seite',
            'product': 'Produkt',
            'event': 'Event',
            'portfolio': 'Portfolio',
            'team': 'Team-Mitglied',
            'testimonial': 'Testimonial',
            'faq': 'FAQ',
            'service': 'Service'
        };
        return labels[postType] || 'Beitrag';
    }

    function getTemplatePathForPostType(postType, themeName) {
        var templatePaths = {
            'post': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle',
            'page': '%2Fwp_template%2F' + themeName + '%2F%2Fpage',
            'product': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-product',
            'event': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-event',
            'portfolio': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-portfolio',
            'team': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-team',
            'testimonial': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-testimonial',
            'faq': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-faq',
            'service': '%2Fwp_template%2F' + themeName + '%2F%2Fsingle-service'
        };
        return templatePaths[postType] || '%2Fwp_template%2F' + themeName + '%2F%2Fsingle';
    }

    function buildEditPageLink(dashboardUrl, params, postId, postType, isFrontPage, isHomePage, isCategory, isTag, isAuthor, isArchive, isSearch, is404, termId, authorId) {
        if (isCategory && termId) {
            return '<a href="' + dashboardUrl + 'edit-tags.php?action=edit&taxonomy=category&tag_ID=' + termId + '" class="gbp-edit-page">Kategorie bearbeiten</a>';
        }
        if (isTag && termId) {
            return '<a href="' + dashboardUrl + 'edit-tags.php?action=edit&taxonomy=post_tag&tag_ID=' + termId + '" class="gbp-edit-page">Tag bearbeiten</a>';
        }
        if (isAuthor && authorId) {
            return '<a href="' + dashboardUrl + 'user-edit.php?user_id=' + authorId + '" class="gbp-edit-page">Autor bearbeiten</a>';
        }
        if (postId && postType) {
            return '<a href="' + dashboardUrl + 'post.php?post=' + postId + '&action=edit" class="gbp-edit-page">' + getPostTypeLabel(postType) + ' bearbeiten</a>';
        }
        if (isFrontPage) {
            return '<a href="' + dashboardUrl + 'post.php?post=' + params.front_page_id + '&action=edit" class="gbp-edit-page">Startseite bearbeiten</a>';
        }
        if (isHomePage) {
            return '<a href="' + dashboardUrl + 'post.php?post=' + params.home_page_id + '&action=edit" class="gbp-edit-page">Blog-Seite bearbeiten</a>';
        }
        return '<a href="' + dashboardUrl + 'edit.php" class="gbp-edit-page">Beiträge bearbeiten</a>';
    }

    function buildEditSiteLink(dashboardUrl, themeName, postId, postType, isFrontPage, isHomePage, isCategory, isTag, isAuthor, isArchive, isSearch, is404) {
        if (isFrontPage) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2F&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (isHomePage) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template%2F' + themeName + '%2F%2Findex&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (isCategory || isTag || isArchive) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template%2F' + themeName + '%2F%2Farchive&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (isAuthor) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template%2F' + themeName + '%2F%2Fauthor&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (isSearch) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template%2F' + themeName + '%2F%2Fsearch&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (is404) {
            return '<a href="' + dashboardUrl + 'site-editor.php?p=%2Fwp_template%2F' + themeName + '%2F%2F404&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        if (postId && postType) {
            var path = getTemplatePathForPostType(postType, themeName);
            return '<a href="' + dashboardUrl + 'site-editor.php?p=' + path + '&canvas=edit" class="gbp-edit-site">Template bearbeiten</a>';
        }
        return '<a href="' + dashboardUrl + 'site-editor.php" class="gbp-edit-site">Website bearbeiten</a>';
    }

    $(document).ready(function() {
        initAdminBarReplacement();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminBarReplacement);
    } else {
        initAdminBarReplacement();
    }
})(jQuery);
