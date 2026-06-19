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
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCountElement.textContent = totalItems;
    }
}

// Use event delegation so dynamically loaded products also trigger the cart
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('add-to-cart-btn')) {
        const button = e.target;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const price = parseInt(button.getAttribute('data-price'));

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

async function handleStripeCheckout(cart) {
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
            body: JSON.stringify({ cart: cart })
        });

        if (!response.ok) {
            throw new Error('Stripe session creation failed');
        }

        const data = await response.json();
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

// Handle Checkout Form Submission
const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const paymentMethodForm = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethodForm) return;
        
        const paymentMethod = paymentMethodForm.value;
        const totalElement = document.getElementById('cart-total');
        const amount = totalElement ? totalElement.textContent.replace(/[^0-9]/g, '') : 0;
        const addressInput = document.getElementById('address');
        const phoneInput = document.getElementById('phone');

        if (window.currentUserUid) {
            if (addressInput && addressInput.value) {
                localStorage.setItem('user_address_' + window.currentUserUid, addressInput.value);
            }
            if (phoneInput && phoneInput.value) {
                localStorage.setItem('user_phone_' + window.currentUserUid, phoneInput.value);
            }
            const nameInput = document.getElementById('name');
            if (nameInput && nameInput.value) {
                localStorage.setItem('user_name_' + window.currentUserUid, nameInput.value);
            }
        }

        if (paymentMethod === 'bank') {
            cart = [];
            localStorage.removeItem('cart');
            window.location.href = 'order-success-bank.html';
        } else if (paymentMethod === 'stripe') {
            handleStripeCheckout(cart);
        } else if (paymentMethod === 'paypay') {
            window.location.href = `mock-paypay-checkout.html?amount=${amount}`;
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

    if (typeof MICROCMS_SERVICE_DOMAIN === 'undefined' || typeof MICROCMS_API_KEY === 'undefined') {
        productGrid.innerHTML = '<p style="text-align: center; width: 100%; padding: 40px; color: red;">APIキーが設定されていません。config.jsを確認してください。</p>';
        return;
    }

    productGrid.innerHTML = '<p style="text-align: center; width: 100%; padding: 40px; color: #666;">商品を読み込み中...</p>';

    try {
        const response = await fetch(`https://${MICROCMS_SERVICE_DOMAIN}.microcms.io/api/v1/products`, {
            headers: {
                'X-MICROCMS-API-KEY': MICROCMS_API_KEY
            }
        });

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

            html += `
                <div class="product-card" data-category="${category}">
                    <a href="shop-detail.html?id=${item.id}" style="text-decoration: none; color: inherit; display: block; height: 100%;">
                        <div class="product-img">
                            <img src="${imageUrl}" alt="${title}">
                        </div>
                        <div class="product-info" style="padding-bottom: 0;">
                            <span class="product-category">${String(category).toUpperCase()}</span>
                            <h3 class="product-title">${title}</h3>
                            <p class="product-price">${price}</p>
                        </div>
                    </a>
                    <div style="padding: 0 1rem 1rem;">
                        <button class="add-to-cart-btn btn" style="width: 100%; margin-top: 10px;" data-id="${item.id}" data-name="${title}" data-price="${item.price || 0}">カートに入れる</button>
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

// Dynamic Navigation Authentication Status Link
function updateNavAuthLink() {
    const navUls = document.querySelectorAll('.main-nav ul');
    if (navUls.length === 0) return;

    navUls.forEach(navUl => {
        let authLi = navUl.querySelector('.nav-auth-item');
        if (!authLi) {
            authLi = document.createElement('li');
            authLi.className = 'nav-auth-item';
            // Find the Shop button if exists
            const shopLi = navUl.querySelector('.nav-btn')?.parentNode || navUl.lastElementChild;
            navUl.insertBefore(authLi, shopLi);
        }

        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        if (isLoggedIn) {
            authLi.innerHTML = `<a href="member.html" style="font-weight: bold; color: var(--secondary-color); text-transform: none;"><i class="far fa-user"></i> マイページ</a>`;
        } else {
            authLi.innerHTML = `<a href="member.html" style="text-transform: none;"><i class="far fa-user"></i> ログイン</a>`;
        }
    });
}

function initializeAppScripts() {
    fetchShopProducts();
    updateNavAuthLink();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAppScripts);
} else {
    initializeAppScripts();
}
