<!-- Footer -->
</div>
        <div class="col-lg-1 d-none d-lg-block ad-gutter"></div>
    </div>
</div>

<!-- Footer -->
<?php
$freePolls = 10;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['pricing_free_polls']);
        $freePolls = (int)($stmt->fetchColumn() ?? 10);
        if ($freePolls <= 0) $freePolls = 10;
    } catch (Exception $e) {
        $freePolls = 10;
    }
}
?>
<footer class="site-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <img src="https://tentideconsultingservices.com/pollnxt/beta/Backup2/images/logo.png" alt="POLLNXT Logo" class="footer-logo d-block mb-2">
                <p class="mb-0">Create, share, and analyze polls with ease. Free for your first <?php echo (int)$freePolls; ?> polls!</p>
            </div>
            <div class="col-md-3">
                <h6 class="text-white">Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $base ?? ''; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $base ?? ''; ?>pages/all_polls.php">All Polls</a></li>
                    <li><a href="<?php echo $base ?? ''; ?>pages/create_poll.php">Create Poll</a></li>
                    <li><a href="<?php echo $base ?? ''; ?>billing.php">Pricing</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h6 class="text-white">Contact</h6>
                <p class="mb-1"><i class="fas fa-envelope me-2"></i>support@pollnxt.com</p>
                <p class="mb-0"><i class="fas fa-globe me-2"></i>pollnxt.com</p>
            </div>
        </div>
        <hr style="border-color: #636e72;">
        <p class="text-center mb-0">&copy; <?php echo date('Y'); ?> POLLNXT. All rights reserved.</p>
    </div>
</footer>

<!-- Chat BOT: interactive help assistant for common tasks -->
<div id="pollnxt-bot" aria-live="polite">
    <button id="botToggle" class="bot-toggle" aria-expanded="false" title="Need help?">Help</button>
    <div id="botPanel" class="bot-panel" role="dialog" aria-hidden="true">
        <div class="bot-header">
            <strong>POLLNXT Assistant</strong>
            <button id="botClose" class="bot-close" aria-label="Close">&times;</button>
        </div>
        <div class="bot-body">
            <div id="botMessages" class="bot-messages">
                <div class="bot-message bot-welcome">Hi! I can help with: <ul class="mb-0"><li><button class="bot-quick" data-key="register">How to Register</button></li><li><button class="bot-quick" data-key="login">How to Login</button></li><li><button class="bot-quick" data-key="create">How to Create a Poll</button></li><li><button class="bot-quick" data-key="vote">How to Vote</button></li><li><button class="bot-quick" data-key="share">How to Share</button></li></ul></div>
            </div>
        </div>
        <div class="bot-footer">
            <input id="botInput" class="form-control" placeholder="Ask me (e.g. how to create a poll)" aria-label="Ask the assistant">
            <button id="botSend" class="btn btn-primary ms-2">Ask</button>
        </div>
    </div>
</div>

<style>
/* Simple styles for the assistant */
#pollnxt-bot { position: fixed; right: 18px; bottom: 18px; z-index: 1200; font-family: inherit; }
.bot-toggle { background: var(--primary); color: #fff; border: none; padding: 12px 14px; border-radius: 999px; box-shadow: 0 6px 18px rgba(0,0,0,0.15); cursor: pointer; }
.bot-panel { width: 320px; max-width: calc(100vw - 40px); background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 8px; overflow: hidden; display: none; }
.bot-header { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f7f7f8; border-bottom:1px solid #e9ecef; }
.bot-body { padding:10px; max-height: 300px; overflow:auto; }
.bot-messages .bot-message { margin-bottom:10px; font-size:14px; }
.bot-message a { color: var(--primary); text-decoration: underline; }
.bot-quick { background:transparent; border:1px solid #e9ecef; padding:6px 8px; border-radius:6px; cursor:pointer; }
.bot-footer { display:flex; padding:10px; border-top:1px solid #e9ecef; }
.bot-close { background:transparent; border: none; font-size:20px; line-height:1; cursor:pointer; }
</style>

<script>
(function(){
    const panel = document.getElementById('botPanel');
    const toggle = document.getElementById('botToggle');
    const closeBtn = document.getElementById('botClose');
    const messages = document.getElementById('botMessages');
    const input = document.getElementById('botInput');
    const send = document.getElementById('botSend');

    // Canned answers (rendered with server paths)
    const answers = {
        register: `1) Open the registration page: <a href="<?php echo $base ?? ''; ?>auth/register.php">Register</a>.<br>2) Fill name, email and password.<br>3) Verify email if requested and then login.`,
        login: `1) Open <a href="<?php echo $base ?? ''; ?>auth/login.php">Login</a>.<br>2) Enter your email and password.<br>3) Use Google login if enabled (click the Google button).`,
        create: `1) Go to <a href="<?php echo $base ?? ''; ?>pages/create_poll.php">Create Poll</a>.<br>2) Choose a category, write a question and add at least 2 options.<br>3) (Optional) Upload an image and set an end date.<br>4) Click Create Poll.`,
        vote: `1) Visit <a href="<?php echo $base ?? ''; ?>pages/all_polls.php">All Polls</a> or open a poll page.<br>2) Select an option and click Vote.<br>3) You may be required to login before voting depending on poll settings.`,
        share: `1) Open the poll page you want to share.<br>2) Use the social icons (Twitter, Facebook, WhatsApp) or copy the poll link.<br>3) For Twitter, you can use: <a href="https://twitter.com/intent/tweet?text=Check+out+this+poll+on+POLLNXT+<?php echo $base ?? ''; ?>">Share on Twitter</a>.`,
        default: `You can click any quick action buttons or type a question like "how to create a poll".`
    };

    function showPanel(open) {
        panel.style.display = open ? 'block' : 'none';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    toggle.addEventListener('click', function(){ showPanel(panel.style.display !== 'block'); });
    closeBtn.addEventListener('click', function(){ showPanel(false); });

    function appendMessage(html, isUser) {
        const div = document.createElement('div');
        div.className = 'bot-message' + (isUser ? ' bot-user' : ' bot-assistant');
        div.innerHTML = html;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    document.querySelectorAll('.bot-quick').forEach(function(btn){
        btn.addEventListener('click', function(){
            const key = btn.getAttribute('data-key');
            appendMessage(btn.textContent, true);
            const resp = answers[key] || answers.default;
            setTimeout(function(){ appendMessage(resp, false); }, 250);
        });
    });

    send.addEventListener('click', function(){ handleInput(); });
    input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); handleInput(); } });

    function handleInput(){
        const text = input.value.trim();
        if (!text) return;
        appendMessage(h(text), true);
        input.value = '';
        // Simple keyword mapping
        const k = text.toLowerCase();
        let key = 'default';
        if (k.includes('register')) key = 'register';
        else if (k.includes('login')) key = 'login';
        else if (k.includes('create') || k.includes('poll')) key = 'create';
        else if (k.includes('vote')) key = 'vote';
        else if (k.includes('share') || k.includes('social')) key = 'share';
        setTimeout(function(){ appendMessage(answers[key], false); }, 300);
    }

    // basic input sanitize for display
    function h(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'); }
})();
</script>

<!-- Poll countdown timers -->
<script>
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        document.querySelectorAll('.poll-countdown').forEach(function (el) {
            if (el.classList.contains('ended')) return;
            var endDate = new Date(el.dataset.end + 'T23:59:59');
            var diff = endDate - Date.now();
            var textEl = el.querySelector('.countdown-text');
            if (!textEl) return;
            if (diff <= 0) {
                textEl.textContent = 'Poll Ended';
                el.classList.add('ended');
                return;
            }
            var d = Math.floor(diff / 86400000);
            var h = Math.floor((diff % 86400000) / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            textEl.textContent = d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(s);
        });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>
