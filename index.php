<?php get_header(); ?>
<div class="wp-shortener-page">
    <header class="header-nav">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin: 0; font-size: 1.5rem; color: #1e40af;">Myly</h1>
            <div>
                <button id="darkModeToggle" style="margin-right: 1rem; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">🌙</button>
                <nav>
                    <a href="#home">Home</a>
                    <a href="#about">About</a>
                    <a href="#privacy">Privacy</a>
                    <a href="#terms">Terms</a>
                    <a href="#contact">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container" style="padding: 3rem 1rem;">
        <section id="home" class="section fade-in">
            <h2>Myly URL Shortener</h2>
            <p>Shorten your links with ease.</p>
            <div class="minily-shortener-widget">  <!-- Optional: rename class later -->
                <h3>URL Shortener</h3>
                <form id="shortenForm">
                    <input type="url" id="longUrl" placeholder="Enter your long URL here..." required>
                    <button type="submit">Shorten</button>
                </form>
                <div id="resultContainer" style="display: none; margin-top: 1rem;">
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="shortUrl" readonly>
                        <button id="copyBtn">Copy</button>
                        <div id="qrContainer" style="margin-left: 1rem;"></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="ad-placeholder">Ad Placeholder</div>

        <section id="about" class="section fade-in">
            <h3>About</h3>
            <p>A minimalist URL shortener built with WordPress.</p>
        </section>

        <section id="privacy" class="section fade-in">
            <h3>Privacy Policy</h3>
            <p>We do not sell or misuse your data.</p>
        </section>

        <section id="terms" class="section fade-in">
            <h3>Terms of Service</h3>
            <p>By using this service, you agree to our terms.</p>
        </section>

        <section id="contact" class="section fade-in">
            <h3>Contact Us</h3>
            <form id="contactForm" style="max-width: 500px;">
                <input type="text" id="name" placeholder="Your Name" required>
                <input type="email" id="email" placeholder="Your Email" required>
                <input type="text" id="subject" placeholder="Subject" required>
                <textarea id="message" rows="5" placeholder="Your Message" required></textarea>
                <button type="submit">Send Message</button>
            </form>
        </section>
    </main>

    <footer><div class="container">&copy; <?php echo date('Y'); ?> Myly. All rights reserved.</div></footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const html = document.documentElement;
    const toggle = document.getElementById('darkModeToggle');
    const isDark = localStorage.getItem('myly_dark') === 'true';
    if (isDark) { html.classList.add('dark-mode'); toggle.textContent = '☀️'; }

    toggle.addEventListener('click', () => {
        const isDarkMode = html.classList.toggle('dark-mode');
        localStorage.setItem('myly_dark', isDarkMode);
        toggle.textContent = isDarkMode ? '☀️' : '🌙';
    });

    document.getElementById('shortenForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const longUrl = document.getElementById('longUrl').value;
        const data = new FormData();
        data.append('action', 'shorten_url');
        data.append('long_url', longUrl);
        data.append('nonce', myly_vars.nonce);

        const res = await fetch(myly_vars.ajax_url, { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
            const shortUrl = json.data.short_url;
            document.getElementById('shortUrl').value = shortUrl;
            document.getElementById('qrContainer').innerHTML = 
                '<img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(shortUrl) + 
                '" alt="QR Code" style="height: 100px; width: 100px;">';
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('resultContainer').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert('Error: ' + json.data.message);
        }
    });

    document.getElementById('copyBtn').addEventListener('click', function () {
        const input = document.getElementById('shortUrl');
        input.select();
        document.execCommand('copy');
        const t = this.textContent;
        this.textContent = 'Copied!';
        setTimeout(() => this.textContent = t, 2000);
    });

    document.getElementById('contactForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'submit_contact');
        fd.append('nonce', myly_vars.contact_nonce);

        const res = await fetch(myly_vars.ajax_url, { method: 'POST', body: fd });
        const json = await res.json();
        alert(json.success ? 'Message sent!' : 'Failed to send.');
        this.reset();
    });

    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            document.querySelector(a.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
        });
    });
});
</script>

<style>
.dark-mode { background-color: #111827 !important; color: #e5e7eb !important; }
.dark-mode .header-nav, .dark-mode footer, .dark-mode input, .dark-mode button, .dark-mode .minily-shortener-widget { background: #1f2937; color: #e5e7eb; border-color: #4b5563; }
.dark-mode a { color: #93c5fd; }
</style>
