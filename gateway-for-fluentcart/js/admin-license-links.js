(function () {
    'use strict';

    var config = window.rupGatewayAdminLinks;
    if (!config) return;
    var panel = null;
    var panelBody = null;
    var lastRoute = '';
    var observer = null;

    function route() {
        var hash = window.location.hash || '';
        var match = hash.match(/^#\/orders\/(\d+)\/view(?:[/?]|$)/);
        if (match) return { type: 'order', id: match[1] };
        match = hash.match(/^#\/subscriptions\/(\d+)\/view(?:[/?]|$)/);
        return match ? { type: 'subscription', id: match[1] } : null;
    }

    function routeKey(view) {
        return view ? view.type + ':' + view.id : '';
    }

    function element(tag, value) {
        var node = document.createElement(tag);
        node.textContent = value;
        return node;
    }

    function reference(type, id) {
        var label = type === 'order' ? 'Order #' : 'Subscription #';
        if (!id) return document.createTextNode(label + 'unknown');
        var link = element('a', label + id);
        link.href = config.adminPage + '#/' + (type === 'order' ? 'orders' : 'subscriptions') + '/' + id + '/view';
        return link;
    }

    function render(items, view) {
        if (!panelBody || routeKey(route()) !== routeKey(view)) return;
        panelBody.replaceChildren();
        if (!items.length) {
            panelBody.appendChild(element('p', 'No linked licences for this ' + view.type + '.'));
            return;
        }
        var list = document.createElement('ul');
        list.style.cssText = 'list-style:none;margin:0;padding:0;';
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:8px 0;';
            var license = element('a', item.key || 'Licence #' + item.id);
            license.href = item.url || '#';
            license.target = '_blank';
            license.rel = 'noopener noreferrer';
            li.appendChild(license);
            li.appendChild(document.createTextNode(' - ' + (item.status || 'unknown') + ' · '));
            li.appendChild(reference('order', item.orderId));
            if (item.subscriptionId) {
                li.appendChild(document.createTextNode(' · '));
                li.appendChild(reference('subscription', item.subscriptionId));
            }
            list.appendChild(li);
        });
        panelBody.appendChild(list);
    }

    function findCard(view) {
        var app = document.getElementById('fluent_cart_plugin_app');
        if (!app) return null;
        var title = view.type === 'order' ? 'Transaction Details' : 'Subscription Details';
        var cards = app.querySelectorAll('.fct-card');
        for (var c = 0; c < cards.length; c++) {
            var heading = cards[c].querySelector('.fct-card-header-title');
            if (heading && heading.textContent.trim().indexOf(title) === 0) return cards[c];
        }
        var walker = document.createTreeWalker(app, NodeFilter.SHOW_TEXT);
        var textNode;
        while ((textNode = walker.nextNode())) {
            if (textNode.nodeValue.trim() === title && textNode.parentElement) {
                var containingCard = textNode.parentElement.closest('.fct-card') || textNode.parentElement.closest('.el-card');
                if (containingCard) return containingCard;
            }
        }
        var nodes = app.querySelectorAll('h1,h2,h3,h4,div,span,p');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].childElementCount === 0 && nodes[i].textContent.trim() === title) {
                return nodes[i].closest('.fct-card') || nodes[i].closest('.el-card');
            }
        }
        return null;
    }

    function place(view) {
        if (!panel || routeKey(route()) !== routeKey(view)) return;
        var card = findCard(view);
        if (card && (panel.parentNode !== card.parentNode || panel.previousElementSibling !== card)) {
            panel.style.cssText = '';
            card.insertAdjacentElement('afterend', panel);
        } else if (!card && !panel.isConnected) {
            var menu = document.getElementById('fct_admin_menu_holder');
            if (menu) menu.insertAdjacentElement('afterend', panel);
        }
    }

    function refresh() {
        var view = route();
        var key = routeKey(view);
        if (key === lastRoute && (!view || panel)) {
            if (view) place(view);
            return;
        }
        lastRoute = key;
        if (observer) observer.disconnect();
        observer = null;
        if (panel) panel.remove();
        panel = null;
        panelBody = null;
        if (!view) return;
        var menu = document.getElementById('fct_admin_menu_holder');
        var app = document.getElementById('fluent_cart_plugin_app');
        if (!menu || !app) return;
        panel = document.createElement('section');
        panel.className = 'fct-card rup-gateway-admin-links';
        panel.style.cssText = 'max-width:1200px;margin:16px auto;';
        var header = document.createElement('div');
        header.className = 'fct-card-header border-bottom';
        var headerInner = document.createElement('div');
        headerInner.className = 'flex-1';
        var title = element('h2', 'Linked licence keys');
        title.className = 'fct-card-header-title is-small';
        headerInner.appendChild(title);
        header.appendChild(headerInner);
        panelBody = document.createElement('div');
        panelBody.className = 'fct-card-body';
        panelBody.appendChild(element('p', 'Loading licences...'));
        panel.appendChild(header);
        panel.appendChild(panelBody);
        menu.insertAdjacentElement('afterend', panel);
        observer = new MutationObserver(function () { place(view); });
        observer.observe(app, { childList: true, subtree: true });
        place(view);
        var url = config.url + '?action=rup_gateway_fc_admin_license_links&nonce=' + encodeURIComponent(config.nonce) + '&type=' + view.type + '&id=' + view.id;
        fetch(url, { credentials: 'same-origin' }).then(function (response) { return response.json(); }).then(function (response) {
            if (!response.success) throw new Error('Could not load licences.');
            render(response.data || [], view);
            place(view);
        }).catch(function () {
            if (panelBody && routeKey(route()) === key) {
                panelBody.replaceChildren(element('p', 'Could not load linked licences. Check your WordPress login and refresh this page.'));
                place(view);
            }
        });
    }

    window.addEventListener('hashchange', refresh);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh);
    else refresh();
}());
