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
            // (Todo: Implement mobile menu toggle logic)
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

/* --- Cart Logic --- */
let cart = JSON.parse(localStorage.getItem('cart')) || [];

function updateCartUI() {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCountElement.textContent = totalItems;
    }
}

document.querySelectorAll('.add-to-cart-btn').forEach(button => {
    button.addEventListener('click', () => {
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
        alert(`${name} をカートに追加しました。`);
    });
});

// Initialize UI
updateCartUI();

/* --- Category Filter --- */
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelector('.filter-btn.active').classList.remove('active');
        btn.classList.add('active');

        const category = btn.getAttribute('data-category');
        document.querySelectorAll('.product-card').forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

/* --- Checkout Page Logic --- */
function renderCheckout() {
    const itemsList = document.getElementById('cart-items-list');
    const totalElement = document.getElementById('cart-total');

    if (itemsList && totalElement) {
        if (cart.length === 0) {
            itemsList.innerHTML = '<p>カートに商品が入っていません。</p>';
            totalElement.textContent = '¥0';
            return;
        }

        let html = '';
        let total = 0;
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            html += `
                <div class="summary-item">
                    <div>
                        <strong>${item.name}</strong> x ${item.quantity}
                    </div>
                    <div>¥${itemTotal.toLocaleString()}</div>
                </div>
            `;
        });
        itemsList.innerHTML = html;
        totalElement.textContent = `¥${total.toLocaleString()}`;
    }
}

// Handle Checkout Form Submission
const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
        e.preventDefault();
        alert('ご注文ありがとうございます！(デモ実装のため、実際には注文されません)');
        cart = [];
        localStorage.removeItem('cart');
        window.location.href = 'index.html';
    });
}

// Re-run for checkout page
renderCheckout();
