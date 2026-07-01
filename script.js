// Sticky Header Background
const header = document.querySelector('.site-header');
const heroSection = document.querySelector('.hero');

window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

// Smooth Scroll for Anchors
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;

        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            const headerHeight = header.offsetHeight;
            const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - headerHeight;

            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });

            // Close mobile menu if open
            if (mainNav.classList.contains('active')) {
                mainNav.classList.remove('active');
                mobileMenuBtn.classList.remove('active');
            }
        }
    });
});

// Hero Slideshow (Mobile)
const slides = document.querySelectorAll('.hero-item');
if (slides.length > 0) {
    let currentSlide = 0;
    const slideInterval = 4000; // 4 seconds

    function nextSlide() {
        // Only effective if CSS is relying on .active class (mobile)
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }

    setInterval(nextSlide, slideInterval);
}

// Mobile Menu Toggle
const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
const mainNav = document.querySelector('.main-nav');

if (mobileMenuBtn && mainNav) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenuBtn.classList.toggle('active');
        mainNav.classList.toggle('active');
    });
}

/* --- Cart Logic --- */
let cart = JSON.parse(localStorage.getItem('cart')) || [];

function updateCartUI() {
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    document.querySelectorAll('#cart-count, .mobile-cart-count').forEach(countElement => {
        countElement.textContent = totalItems;
    });
    updateMobileCartButton(totalItems);
}

function shouldShowMobileCartButton() {
    return !!document.querySelector('.product-grid, #shop-detail-content');
}

function ensureMobileCartButton() {
    if (!shouldShowMobileCartButton()) return null;

    let mobileCartButton = document.getElementById('mobile-cart-button');
    if (!mobileCartButton) {
        mobileCartButton = document.createElement('a');
        mobileCartButton.id = 'mobile-cart-button';
        mobileCartButton.className = 'mobile-cart-button';
        mobileCartButton.href = 'checkout.html';
        mobileCartButton.setAttribute('aria-label', 'カートを見る');
        mobileCartButton.innerHTML = `
            <span class="mobile-cart-icon"><i class="fas fa-shopping-cart"></i></span>
            <span class="mobile-cart-label">カートを見る</span>
            <span class="mobile-cart-count">0</span>
        `;
        document.body.appendChild(mobileCartButton);
    }

    return mobileCartButton;
}

function updateMobileCartButton(totalItems = cart.reduce((sum, item) => sum + item.quantity, 0)) {
    const mobileCartButton = ensureMobileCartButton();
    if (!mobileCartButton) return;

    const countElement = mobileCartButton.querySelector('.mobile-cart-count');
    if (countElement) countElement.textContent = totalItems;
    mobileCartButton.classList.toggle('has-items', totalItems > 0);
}

// Use event delegation so dynamically loaded products also trigger the cart
document.addEventListener('click', (e) => {
    const button = e.target.closest('.add-to-cart-btn[data-id]');
    if (button) {
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const price = parseInt(button.getAttribute('data-price'));

        if (!id || !name || Number.isNaN(price)) return;

        const existingItem = cart.find(item => item.id === id);
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.push({ id, name, price, quantity: 1 });
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartUI();
        showCartToast(name);
    }
});

function showCartToast(name) {
    let toast = document.getElementById('cart-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'cart-toast';
        toast.className = 'cart-toast';
        document.body.appendChild(toast);
    }
    
    toast.innerHTML = `
        <span class="cart-toast-text">${name} をカートに追加しました。</span>
        <a href="checkout.html" class="cart-toast-btn">カートを見る</a>
    `;
    
    // Reset animation
    toast.classList.remove('show');
    void toast.offsetWidth; // force reflow
    toast.classList.add('show');
    
    // Auto hide
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

// Initialize UI
updateCartUI();

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

const SHOP_MEMBER_PROFILE_KEY = 'shopMemberProfile';
const SHOP_ORDER_HISTORY_KEY = 'shopOrderHistory';

function readJsonStorage(key, fallback = null) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
}

function getShopMemberProfile() {
    if (localStorage.getItem('isLoggedIn') !== 'true') return null;

    const uid = localStorage.getItem('auth_user_uid') || '';
    const email = localStorage.getItem('auth_user_email') || '';
    const savedProfile = uid ? readJsonStorage('user_profile_' + uid, {}) : {};
    const shopProfile = readJsonStorage(SHOP_MEMBER_PROFILE_KEY, {});

    const profile = {
        uid,
        name: savedProfile.name || shopProfile.name || localStorage.getItem('auth_user_name') || email,
        email: savedProfile.email || shopProfile.email || email,
        phone: savedProfile.phone || shopProfile.phone || (uid ? localStorage.getItem('user_phone_' + uid) : '') || '',
        address: savedProfile.address || shopProfile.address || (uid ? localStorage.getItem('user_address_' + uid) : '') || '',
    };

    if (!profile.uid && !profile.email) return null;
    return profile;
}

function saveShopMemberProfile(profile) {
    if (!profile || (!profile.uid && !profile.email)) return;
    const clean = {
        uid: profile.uid || '',
        name: profile.name || '',
        email: profile.email || '',
        phone: profile.phone || '',
        address: profile.address || '',
        updatedAt: new Date().toISOString(),
    };
    localStorage.setItem(SHOP_MEMBER_PROFILE_KEY, JSON.stringify(clean));
}

function getShopOrderHistory() {
    const history = readJsonStorage(SHOP_ORDER_HISTORY_KEY, []);
    return Array.isArray(history) ? history : [];
}

function appendShopOrderHistory(order) {
    if (!order || !order.order_id) return;
    const history = getShopOrderHistory();
    const normalized = {
        ...order,
        saved_at: order.saved_at || new Date().toISOString(),
    };
    const withoutDuplicate = history.filter(item => item.order_id !== normalized.order_id);
    withoutDuplicate.unshift(normalized);
    localStorage.setItem(SHOP_ORDER_HISTORY_KEY, JSON.stringify(withoutDuplicate.slice(0, 12)));
}

function formatShopOrderForCrm(order) {
    if (!order) return '';
    const customer = order.customer || {};
    const items = Array.isArray(order.items) ? order.items : [];
    const itemLines = items.map((item) => {
        const name = item.name || '商品';
        const quantity = item.quantity || 1;
        const price = Number(item.price || 0);
        return `- ${name} / 数量: ${quantity} / 単価: ${price.toLocaleString()}円`;
    });
    const amount = Number(order.amount_total || items.reduce((sum, item) => {
        return sum + (Number(item.price || 0) * Number(item.quantity || 0));
    }, 0));
    const date = order.saved_at || order.completed_at || order.created_at || '';

    return [
        'Highlanderネットショップ注文',
        `注文番号: ${order.order_id || ''}`,
        `注文日時: ${date}`,
        `GoogleログインUID: ${order.member_uid || customer.uid || ''}`,
        `Googleメール: ${order.customer_email || customer.email || ''}`,
        `氏名: ${customer.name || ''}`,
        `電話番号: ${customer.phone || ''}`,
        `配送先住所: ${customer.address || ''}`,
        `決済方法: ${order.payment_method || ''}`,
        `決済状況: ${order.status_label || order.payment_status || ''}`,
        `合計金額: ${amount ? `${amount.toLocaleString()}円` : ''}`,
        '商品:',
        itemLines.length ? itemLines.join('\n') : '- 注文内容なし',
    ].join('\n');
}

window.HighlanderShop = {
    getMemberProfile: getShopMemberProfile,
    saveMemberProfile: saveShopMemberProfile,
    getOrderHistory: getShopOrderHistory,
    appendOrderHistory: appendShopOrderHistory,
    formatOrderForCrm: formatShopOrderForCrm,
};

/* --- Brand Filter Logic --- */
const filterBtns = document.querySelectorAll('.brand-filter .filter-btn');
const brandCards = document.querySelectorAll('.brand-grid .brand-card');

if (filterBtns.length > 0 && brandCards.length > 0) {
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            btn.classList.add('active');

            const filterValue = btn.getAttribute('data-filter');

            brandCards.forEach(card => {
                const category = card.getAttribute('data-category');
                if (filterValue === 'all' || category === filterValue) {
                    card.style.display = 'flex';
                    // Trigger a small animation
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.style.opacity = '1';
                    }, 50);
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
}

/* --- Category Filter (General - Existing) --- */

/* --- Checkout Page Logic --- */
function renderCheckout() {
    const itemsList = document.getElementById('cart-items-list');
    const totalElement = document.getElementById('cart-total');

    if (itemsList && totalElement) {
        if (cart.length === 0) {
            itemsList.innerHTML = '<p style="padding: 20px 0; color: #888;">カートに商品が入っていません。</p>';
            totalElement.textContent = '¥0';
            const submitBtn = document.querySelector('#checkout-form button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        let html = '';
        let total = 0;
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            html += `
                <div class="summary-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee;">
                    <div style="flex: 1;">
                        <strong style="display: block; font-size: 1rem; color: #333;">${item.name}</strong>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                            <button type="button" onclick="changeQuantity('${item.id}', -1)" style="width: 26px; height: 26px; border: 1px solid #ccc; background: #fff; cursor: pointer; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">-</button>
                            <span style="font-weight: 500; min-width: 20px; text-align: center;">${item.quantity}</span>
                            <button type="button" onclick="changeQuantity('${item.id}', 1)" style="width: 26px; height: 26px; border: 1px solid #ccc; background: #fff; cursor: pointer; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">+</button>
                            <button type="button" onclick="removeFromCart('${item.id}')" style="margin-left: 15px; border: none; background: none; color: #c62828; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px; font-weight: bold;"><i class="fas fa-trash-alt"></i> 削除</button>
                        </div>
                    </div>
                    <div style="font-weight: bold; font-size: 1.1rem; color: var(--primary-color);">¥${itemTotal.toLocaleString()}</div>
                </div>
            `;
        });
        itemsList.innerHTML = html;
        totalElement.textContent = `¥${total.toLocaleString()}`;
        
        const submitBtn = document.querySelector('#checkout-form button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
    }
}

window.changeQuantity = function(id, amount) {
    const item = cart.find(item => item.id === id);
    if (item) {
        item.quantity += amount;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartUI();
        renderCheckout();
    }
};

window.removeFromCart = function(id) {
    cart = cart.filter(i => i.id !== id);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartUI();
    renderCheckout();
};

async function handleStripeCheckout(cart, orderPayload) {
    const submitBtn = document.querySelector('#checkout-form button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '注文を確定する';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = '決済画面へ遷移中...';
    }

    try {
        const response = await fetch('/api/create-checkout-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart: cart.map(item => ({
                    id: item.id,
                    quantity: item.quantity,
                })),
                customer: orderPayload.customer,
                order_id: orderPayload.order_id,
            })
        });

        if (!response.ok) {
            throw new Error('Stripe session creation failed');
        }

        const data = await response.json();
        if (data.order_id) {
            orderPayload.order_id = data.order_id;
        }
        localStorage.setItem('pendingStripeOrder', JSON.stringify(orderPayload));
        // Redirect directly to Stripe Checkout URL (or mock URL if fallback)
        window.location.href = data.url;
    } catch (error) {
        console.error('Stripe Checkout Error:', error);
        alert('決済処理の開始に失敗しました。');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}

// ===== HubLink CRM 連携 =====
// 注文内容を組み立て、自ドメインの /api/order-to-hublink 経由でCRMへ送る。
// 秘密鍵はサーバー側のみ。失敗しても購入フローは止めない（疎結合）。
function buildOrderPayload(paymentMethod, paymentStatus) {
    const val = (id) => (document.getElementById(id)?.value || '').trim();
    const uid = window.currentUserUid || localStorage.getItem('auth_user_uid') || '';
    const memberProfile = getShopMemberProfile();
    const shouldSaveDeliveryProfile = document.getElementById('save-delivery-profile')?.checked !== false;
    return {
        order_id: 'ord_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        customer: {
            name: val('name'),
            email: val('email'),
            phone: val('phone'),
            address: val('address'),
            uid,
            member_logged_in: !!memberProfile,
            identity_provider: uid ? 'google' : 'guest',
            delivery_profile_saved: Boolean(uid && shouldSaveDeliveryProfile),
        },
        items: cart.map(i => ({ id: i.id, name: i.name, price: i.price, quantity: i.quantity })),
        payment_method: paymentMethod,
        payment_status: paymentStatus,
        member: memberProfile ? {
            uid: memberProfile.uid,
            email: memberProfile.email,
            name: memberProfile.name,
        } : null,
    };
}

async function notifyHubLinkOrder(payload) {
    try {
        await fetch('/api/order-to-hublink', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
    } catch (e) {
        // CRM連携の失敗は購入を妨げない
        console.warn('CRM連携に失敗しましたが注文は継続します:', e);
    }
}

function persistCheckoutMemberFields() {
    const uid = window.currentUserUid || localStorage.getItem('auth_user_uid') || '';
    if (!uid && localStorage.getItem('isLoggedIn') !== 'true') return;
    const shouldSaveDeliveryProfile = document.getElementById('save-delivery-profile')?.checked !== false;

    const profile = {
        uid,
        name: (document.getElementById('name')?.value || '').trim(),
        email: (document.getElementById('email')?.value || '').trim(),
        phone: (document.getElementById('phone')?.value || '').trim(),
        address: (document.getElementById('address')?.value || '').trim(),
    };

    if (profile.name) localStorage.setItem('auth_user_name', profile.name);
    if (profile.email) localStorage.setItem('auth_user_email', profile.email);

    if (!shouldSaveDeliveryProfile) return;

    if (uid) {
        localStorage.setItem('user_address_' + uid, profile.address);
        localStorage.setItem('user_phone_' + uid, profile.phone);
        localStorage.setItem('user_name_' + uid, profile.name);
        localStorage.setItem('user_profile_' + uid, JSON.stringify(profile));
    }
    saveShopMemberProfile(profile);
}

function buildLocalOrderRecord(orderPayload, overrides = {}) {
    const amountTotal = orderPayload.items.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0);
    return {
        order_id: orderPayload.order_id,
        customer: orderPayload.customer,
        items: orderPayload.items,
        amount_total: amountTotal,
        currency: 'jpy',
        payment_method: orderPayload.payment_method,
        payment_status: orderPayload.payment_status,
        member_uid: orderPayload.customer.uid || '',
        customer_email: orderPayload.customer.email || '',
        created_at: new Date().toISOString(),
        ...overrides,
    };
}

// Handle Checkout Form Submission
const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const paymentMethodForm = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethodForm) return;

        const paymentMethod = paymentMethodForm.value;
        const totalElement = document.getElementById('cart-total');
        const amount = totalElement ? totalElement.textContent.replace(/[^0-9]/g, '') : 0;
        persistCheckoutMemberFields();

        // カートを変更する前に注文内容を確定（確定時点の支払いは pending）
        const orderPayload = buildOrderPayload(paymentMethod, 'pending');

        if (paymentMethod === 'bank') {
            orderPayload.payment_status = 'bank_transfer_pending';
            const localOrder = buildLocalOrderRecord(orderPayload, {
                payment_status: 'bank_transfer_pending',
                status_label: '銀行振込待ち',
            });
            localStorage.setItem('lastShopOrder', JSON.stringify(localOrder));
            appendShopOrderHistory(localOrder);
            await notifyHubLinkOrder(orderPayload);
            cart = [];
            localStorage.removeItem('cart');
            window.location.href = 'order-success-bank.html';
        } else if (paymentMethod === 'stripe') {
            handleStripeCheckout(cart, orderPayload);
        } else if (paymentMethod === 'paypay') {
            orderPayload.payment_status = 'paypay_pending';
            const localOrder = buildLocalOrderRecord(orderPayload, {
                payment_status: 'paypay_pending',
                status_label: 'PayPay決済待ち',
            });
            localStorage.setItem('pendingLocalOrder', JSON.stringify(localOrder));
            await notifyHubLinkOrder(orderPayload);
            window.location.href = `mock-paypay-checkout.html?amount=${amount}&order_id=${encodeURIComponent(orderPayload.order_id)}`;
        }
    });
}

// Re-run for checkout page
renderCheckout();

/* --- Sticky Footer Scroll Reveal --- */
let lastScrollTop = 0;

window.addEventListener('scroll', () => {
    const stickyFooter = document.getElementById('stickyFooter');
    if (!stickyFooter) return;

    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    // Determine scroll direction
    if (scrollTop > lastScrollTop && scrollTop > 50) {
        // Scrolling DOWN -> Show Footer
        stickyFooter.classList.add('show');
    } else if (scrollTop < lastScrollTop) {
        // Scrolling UP -> Hide Footer
        stickyFooter.classList.remove('show');
    }

    // Hide at the very top of the page
    if (scrollTop <= 50) {
        stickyFooter.classList.remove('show');
    }

    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
});

/* --- microCMS Shop Integration --- */
async function fetchShopProducts() {
    const productGrid = document.querySelector('.product-grid');
    if (!productGrid || !document.querySelector('.shop-filters')) return;

    productGrid.innerHTML = '<p style="text-align: center; width: 100%; padding: 40px; color: #666;">商品を読み込み中...</p>';

    try {
        const response = await fetch('/api/microcms-products');

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.contents || data.contents.length === 0) {
            productGrid.innerHTML = '<p style="text-align: center; width: 100%; padding: 40px; color: #666;">現在、販売中の商品はございません。</p>';
            return;
        }

        let html = '';
        data.contents.forEach(item => {
            const title = item.title || '商品名なし';
            const safeTitle = escapeHtml(title);
            const price = item.price ? `¥${item.price.toLocaleString()}` : '価格未定';
            const imageUrl = item.image && item.image.url ? item.image.url : 'logo.png';
            let category = 'all';
            if (item.category) {
                // Determine category format (microCMS array of objects vs string)
                if (Array.isArray(item.category)) {
                    category = item.category.length > 0 ? item.category[0] : 'all';
                } else {
                    category = item.category;
                }
            }
            const safeCategory = escapeHtml(String(category).toUpperCase());
            const safeImageUrl = escapeHtml(imageUrl);

            html += `
                <div class="product-card" data-category="${escapeHtml(category)}">
                    <a href="shop-detail.html?id=${encodeURIComponent(item.id)}" style="text-decoration: none; color: inherit; display: block; height: 100%;">
                        <div class="product-img">
                            <img src="${safeImageUrl}" alt="${safeTitle}">
                        </div>
                        <div class="product-info" style="padding-bottom: 0;">
                            <span class="product-category">${safeCategory}</span>
                            <h3 class="product-title">${safeTitle}</h3>
                            <p class="product-price">${price}</p>
                        </div>
                    </a>
                    <div style="padding: 0 1rem 1rem;">
                        <button class="add-to-cart-btn btn" style="width: 100%; margin-top: 10px;" data-id="${escapeHtml(item.id)}" data-name="${safeTitle}" data-price="${item.price || 0}">カートに入れる</button>
                    </div>
                </div>
            `;
        });

        productGrid.innerHTML = html;

        // Re-apply filters if already selected
        const activeFilterBtn = document.querySelector('.shop-filters .filter-btn.active');
        if (activeFilterBtn) {
            const filterValue = activeFilterBtn.getAttribute('data-category');
            applyShopFilter(filterValue);
        }

    } catch (error) {
        console.error('Error fetching products from microCMS:', error);
        productGrid.innerHTML = '<p style="text-align: center; width: 100%; padding: 40px; color: red;">商品の読み込みに失敗しました。</p>';
    }
}

// category UI filtering logic
function applyShopFilter(filterValue) {
    const productCards = document.querySelectorAll('.product-grid .product-card');
    productCards.forEach(card => {
        const category = card.getAttribute('data-category');
        if (filterValue === 'all' || category === filterValue || (category && category.includes(filterValue))) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

const shopFilterBtns = document.querySelectorAll('.shop-filters .filter-btn');
if (shopFilterBtns.length > 0) {
    shopFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            shopFilterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filterValue = btn.getAttribute('data-category');
            applyShopFilter(filterValue);
        });
    });
}

// Keep the global navigation focused on the shop entry only.
function updateNavAuthLink() {
    const navUls = document.querySelectorAll('.main-nav ul');
    if (navUls.length === 0) return;

    navUls.forEach(navUl => {
        navUl.querySelectorAll('.nav-auth-item').forEach(item => item.remove());
        navUl.querySelectorAll('.shop-nav-account').forEach(link => link.remove());

        const shopLink = [...navUl.querySelectorAll('a.nav-btn')]
            .find(link => (link.getAttribute('href') || '').includes('shop.html'));
        if (!shopLink) return;

        const shopLi = shopLink.closest('li');
        if (!shopLi) return;

        shopLi.classList.add('shop-nav-item');
        shopLink.classList.add('shop-nav-primary');
        shopLink.innerHTML = '<span>ネットショップ</span>';
    });
}

function initializeAppScripts() {
    fetchShopProducts();
    updateNavAuthLink();
    ensureMobileCartButton();
    updateCartUI();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAppScripts);
} else {
    initializeAppScripts();
}
