// session_timeout.js — Centralized JavaScript for Inactivity Session Timeout
(function() {
    // Disable timeout for admin
    if (window.location.pathname.includes('/admin/')) {
        return;
    }

    let lastActive = Date.now();
    const idleLimit = 300000; // 5 minutes in milliseconds (300,000 ms)
    const checkInterval = 1000; // Check every second

    // Reset timer on user interaction
    function resetTimer() {
        lastActive = Date.now();
    }

    const events = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];
    events.forEach(event => {
        document.addEventListener(event, resetTimer, { passive: true });
    });

    const intervalId = setInterval(() => {
        const elapsed = Date.now() - lastActive;
        if (elapsed >= idleLimit) {
            clearInterval(intervalId);
            // Inactivity timeout reached!
            triggerAutoLogout();
        }
    }, checkInterval);

    function triggerAutoLogout() {
        // Determine correct redirect and endpoint relative path based on location
        const isSubdir = window.location.pathname.includes('/admin/') || 
                         window.location.pathname.includes('/faculty/') || 
                         window.location.pathname.includes('/student/') || 
                         window.location.pathname.includes('/police-partner/');
        const logoutEndpoint = isSubdir ? '../auto_logout.php' : 'auto_logout.php';
        const loginPage = isSubdir ? '../login.php?idle=1' : 'login.php?idle=1';

        // Destroy backend session first
        fetch(logoutEndpoint, { method: 'POST' })
            .then(() => {
                showTimeoutModal(loginPage);
            })
            .catch(() => {
                // Fallback in case of network issue
                showTimeoutModal(loginPage);
            });
    }

    function showTimeoutModal(loginPage) {
        if (document.getElementById('sessionTimeoutOverlay')) return;

        // CSS Injecting for dim overlay and clean rectangular card
        const style = document.createElement('style');
        style.textContent = `
            .session-timeout-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(4px);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .session-timeout-card {
                background: #ffffff;
                padding: 2.5rem;
                border-radius: 12px;
                box-shadow: 0 15px 45px rgba(0, 0, 0, 0.15);
                max-width: 420px;
                width: 90%;
                text-align: center;
                border: 1px solid rgba(0, 0, 0, 0.06);
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .session-timeout-title {
                font-size: 1.45rem;
                font-weight: 700;
                color: #1b4332;
                margin-top: 0;
                margin-bottom: 0.85rem;
            }
            .session-timeout-text {
                font-size: 0.95rem;
                color: #6c757d;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .session-timeout-btn {
                background: #2d6a4f;
                color: #ffffff;
                border: none;
                padding: 0.8rem 2rem;
                border-radius: 6px;
                font-size: 0.95rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.15s ease-in-out;
                width: 100%;
            }
            .session-timeout-btn:hover {
                background: #1b4332;
            }
        `;
        document.head.appendChild(style);

        const overlay = document.createElement('div');
        overlay.id = 'sessionTimeoutOverlay';
        overlay.className = 'session-timeout-overlay';
        
        // Prevent clicking outside to close
        overlay.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        const card = document.createElement('div');
        card.className = 'session-timeout-card';

        const title = document.createElement('h2');
        title.className = 'session-timeout-title';
        title.textContent = 'Log Out';

        const text = document.createElement('p');
        text.className = 'session-timeout-text';
        text.textContent = 'You have been automatically logged out due to inactivity.';

        const btn = document.createElement('button');
        btn.className = 'session-timeout-btn';
        btn.textContent = 'Ok';
        btn.onclick = () => {
            window.location.href = loginPage;
        };

        card.appendChild(title);
        card.appendChild(text);
        card.appendChild(btn);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
    }
})();
