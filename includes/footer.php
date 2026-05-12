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
    <button id="botDismiss" class="bot-dismiss" type="button" aria-label="Minimize Sara chatbot">&times;</button>
    <button id="botToggle" class="bot-toggle" aria-expanded="false" title="Chat with Sara">
        <span class="bot-toggle-avatar">
            <img src="<?php echo $base ?? ''; ?>assets/images/sara-bot.png" alt="Sara chatbot assistant">
        </span>
        <span class="bot-toggle-copy">
            <span class="bot-toggle-label">Chat with Sara</span>
            <span class="bot-toggle-subtitle">Tap for quick help</span>
        </span>
    </button>
    <button id="botMiniToggle" class="bot-mini-toggle" type="button" aria-label="Open Sara chatbot" title="Open support chat">
        <i class="fas fa-headset" aria-hidden="true"></i>
    </button>
    <div id="botPanel" class="bot-panel" role="dialog" aria-hidden="true">
        <div class="bot-header">
            <div class="bot-header-profile">
                <img src="<?php echo $base ?? ''; ?>assets/images/sara-bot.png" alt="Sara">
                <div>
                    <strong>Sara</strong>
                    <div class="bot-header-status">POLLNXT Assistant</div>
                </div>
            </div>
            <div class="bot-header-actions">
                <button id="botMute" class="bot-icon-button" type="button" aria-pressed="false" aria-label="Mute speaker">
                    <i class="fas fa-volume-up" aria-hidden="true"></i>
                </button>
                <button id="botClose" class="bot-close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="bot-body">
            <div id="botMessages" class="bot-messages">
                <div class="bot-message bot-welcome">
                    <div class="bot-message-title">Hi, I'm Sara. I can help with:</div>
                    <div class="bot-quick-list">
                        <button class="bot-quick" data-key="register">How to Register</button>
                        <button class="bot-quick" data-key="login">How to Login</button>
                        <button class="bot-quick" data-key="create">How to Create a Poll</button>
                        <button class="bot-quick" data-key="vote">How to Vote</button>
                        <button class="bot-quick" data-key="share">How to Share</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="bot-footer">
            <input id="botInput" class="form-control" placeholder="Ask me (e.g. how to create a poll)" aria-label="Ask the assistant">
            <button id="botSend" class="btn btn-primary ms-2">Ask</button>
        </div>
    </div>
</div>

<style>
/* Floating assistant */
#pollnxt-bot { position: fixed; right: 18px; bottom: 18px; z-index: 1200; font-family: inherit; display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.bot-dismiss { position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border: 0; border-radius: 50%; background: #1f2d3f; color: #fff; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 6px 14px rgba(18, 38, 58, 0.22); cursor: pointer; line-height: 1; font-size: 14px; z-index: 2; }
.bot-dismiss:hover { background: #12263a; }
.bot-toggle { display: flex; align-items: center; gap: 12px; min-width: 220px; background: #ffffff; color: #12263a; border: 1px solid rgba(0, 175, 145, 0.18); padding: 10px 14px 10px 10px; border-radius: 999px; box-shadow: 0 12px 30px rgba(18, 38, 58, 0.16); cursor: pointer; text-align: left; animation: saraPulse 2.6s ease-in-out infinite; }
.bot-mini-toggle { width: 58px; height: 58px; border: 0; border-radius: 50%; background: linear-gradient(135deg, #00af91 0%, #00cec9 100%); color: #fff; display: none; align-items: center; justify-content: center; box-shadow: 0 14px 30px rgba(0, 175, 145, 0.3); cursor: pointer; animation: saraPulse 2.6s ease-in-out infinite; }
.bot-mini-toggle i { font-size: 22px; }
.bot-toggle-avatar { width: 52px; height: 52px; flex: 0 0 52px; border-radius: 50%; overflow: hidden; border: 3px solid #ffffff; outline: 1px solid rgba(0, 175, 145, 0.28); box-shadow: 0 6px 14px rgba(0, 175, 145, 0.28); background: #ffffff; }
.bot-toggle-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bot-toggle-copy { display: flex; flex-direction: column; line-height: 1.2; }
.bot-toggle-label { font-weight: 700; font-size: 16px; color: #12263a; }
.bot-toggle-subtitle { font-size: 12px; color: #5c6b7a; }
.bot-panel { width: 360px; max-width: calc(100vw - 24px); background: #fff; border-radius: 20px; box-shadow: 0 18px 44px rgba(18, 38, 58, 0.22); overflow: hidden; display: none; border: 1px solid rgba(0, 175, 145, 0.16); }
.bot-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px; background: linear-gradient(135deg, #f5fffc 0%, #ecf9f5 100%); border-bottom: 1px solid #e6f2ee; }
.bot-header-profile { display: flex; align-items: center; gap: 10px; }
.bot-header-profile img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; display: block; }
.bot-header-status { font-size: 12px; color: #5c6b7a; }
.bot-header-actions { display: flex; align-items: center; gap: 6px; }
.bot-icon-button,
.bot-close { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1px solid #d9e7e2; background: #fff; color: #284255; cursor: pointer; }
.bot-icon-button.is-muted { color: #b02a37; border-color: rgba(176, 42, 55, 0.22); background: #fff4f5; }
.bot-body { padding: 12px; max-height: 360px; overflow: auto; background: linear-gradient(180deg, #ffffff 0%, #f9fcfb 100%); }
.bot-messages .bot-message { margin-bottom: 12px; font-size: 14px; line-height: 1.5; }
.bot-message-title { margin-bottom: 10px; font-weight: 600; color: #183247; }
.bot-message a { color: var(--primary); text-decoration: underline; }
.bot-welcome,
.bot-assistant { background: #f2fbf8; border: 1px solid #dcefe8; border-radius: 16px 16px 16px 6px; padding: 12px; color: #173347; }
.bot-user { margin-left: auto; max-width: 85%; background: var(--primary); color: #fff; border-radius: 16px 16px 6px 16px; padding: 10px 12px; }
.bot-quick-list { display: flex; flex-wrap: wrap; gap: 8px; }
.bot-quick { background: #ffffff; border: 1px solid #d7ebe5; padding: 8px 10px; border-radius: 999px; cursor: pointer; color: #173347; transition: all 0.2s ease; }
.bot-quick:hover { background: #eaf8f3; border-color: #b8ddd1; }
.bot-footer { display: flex; padding: 12px; border-top: 1px solid #e6f2ee; background: #fff; }
.bot-footer .form-control { border-radius: 12px; }

@keyframes saraPulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 12px 30px rgba(18, 38, 58, 0.16);
    }
    50% {
        transform: scale(1.025);
        box-shadow: 0 16px 36px rgba(0, 175, 145, 0.24);
    }
}

@media (max-width: 991.98px) {
    #pollnxt-bot { right: 12px; bottom: 12px; left: auto; align-items: flex-end; }
    .bot-toggle { min-width: 220px; width: auto; max-width: calc(100vw - 24px); }
    .bot-panel { width: min(360px, calc(100vw - 24px)); max-width: calc(100vw - 24px); }
}
</style>

<script>
(function(){
    const panel = document.getElementById('botPanel');
    const bot = document.getElementById('pollnxt-bot');
    const toggle = document.getElementById('botToggle');
    const miniToggle = document.getElementById('botMiniToggle');
    const dismissBtn = document.getElementById('botDismiss');
    const closeBtn = document.getElementById('botClose');
    const muteBtn = document.getElementById('botMute');
    const messages = document.getElementById('botMessages');
    const input = document.getElementById('botInput');
    const send = document.getElementById('botSend');
    const synth = 'speechSynthesis' in window ? window.speechSynthesis : null;
    let isMuted = false;
    let lastSpokenText = '';
    let selectedVoice = null;

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
        if (!open && synth) {
            synth.cancel();
        }
    }

    function setMinimized(minimized) {
        toggle.style.display = minimized ? 'none' : 'flex';
        dismissBtn.style.display = minimized ? 'none' : 'inline-flex';
        panel.style.display = minimized ? 'none' : panel.style.display;
        panel.setAttribute('aria-hidden', minimized ? 'true' : panel.getAttribute('aria-hidden'));
        miniToggle.style.display = minimized ? 'inline-flex' : 'none';
        if (minimized && synth) {
            synth.cancel();
        }
        try {
            window.sessionStorage.setItem('pollnxtSaraMinimized', minimized ? '1' : '0');
        } catch (e) {}
    }

    toggle.addEventListener('click', function(){ showPanel(panel.style.display !== 'block'); });
    closeBtn.addEventListener('click', function(){ showPanel(false); });
    dismissBtn.addEventListener('click', function(){ setMinimized(true); });
    miniToggle.addEventListener('click', function(){ setMinimized(false); });

    function appendMessage(html, isUser, speechText) {
        const div = document.createElement('div');
        div.className = 'bot-message' + (isUser ? ' bot-user' : ' bot-assistant');
        div.innerHTML = html;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        if (!isUser) {
            speakText(speechText || htmlToSpeechText(html));
        }
    }

    function htmlToSpeechText(html) {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        return (temp.textContent || temp.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function speakText(text) {
        if (!synth || isMuted || !text) {
            return;
        }
        lastSpokenText = text;
        synth.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        if (selectedVoice) {
            utterance.voice = selectedVoice;
        }
        utterance.rate = 0.96;
        utterance.pitch = 1.08;
        utterance.volume = 1;
        synth.speak(utterance);
    }

    function scoreVoice(voice) {
        const name = (voice.name || '').toLowerCase();
        const lang = (voice.lang || '').toLowerCase();
        let score = 0;
        if (lang.indexOf('en') === 0) score += 5;
        if (voice.localService) score += 3;
        if (name.includes('female')) score += 12;
        if (name.includes('woman')) score += 12;
        if (name.includes('zira')) score += 11;
        if (name.includes('samantha')) score += 11;
        if (name.includes('susan')) score += 10;
        if (name.includes('victoria')) score += 10;
        if (name.includes('karen')) score += 10;
        if (name.includes('moira')) score += 10;
        if (name.includes('zira')) score += 10;
        if (name.includes('aria')) score += 10;
        if (name.includes('jenny')) score += 10;
        if (name.includes('libby')) score += 10;
        if (name.includes('sonia')) score += 10;
        if (name.includes('natasha')) score += 10;
        if (name.includes('neural')) score += 4;
        if (name.includes('male')) score -= 12;
        if (name.includes('man')) score -= 8;
        if (name.includes('david')) score -= 8;
        if (name.includes('mark')) score -= 8;
        if (name.includes('george')) score -= 8;
        if (name.includes('james')) score -= 8;
        return score;
    }

    function pickVoice() {
        if (!synth) {
            return;
        }
        const voices = synth.getVoices();
        if (!voices.length) {
            return;
        }
        selectedVoice = voices
            .slice()
            .sort(function(a, b){ return scoreVoice(b) - scoreVoice(a); })[0] || null;
    }

    function setMuted(nextMuted) {
        isMuted = nextMuted;
        muteBtn.setAttribute('aria-pressed', nextMuted ? 'true' : 'false');
        muteBtn.setAttribute('aria-label', nextMuted ? 'Unmute speaker' : 'Mute speaker');
        muteBtn.classList.toggle('is-muted', nextMuted);
        muteBtn.innerHTML = nextMuted
            ? '<i class="fas fa-volume-mute" aria-hidden="true"></i>'
            : '<i class="fas fa-volume-up" aria-hidden="true"></i>';
        if (nextMuted && synth) {
            synth.cancel();
        }
        if (!nextMuted && lastSpokenText) {
            speakText(lastSpokenText);
        }
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
    muteBtn.addEventListener('click', function(){ setMuted(!isMuted); });

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

    if (!synth) {
        muteBtn.disabled = true;
        muteBtn.title = 'Speech is not supported in this browser';
    } else {
        pickVoice();
        if (typeof synth.onvoiceschanged !== 'undefined') {
            synth.onvoiceschanged = pickVoice;
        }
    }

    try {
        if (window.sessionStorage.getItem('pollnxtSaraMinimized') === '1') {
            setMinimized(true);
        }
    } catch (e) {}
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
