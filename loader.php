<?php
?>
<htmL>
<!-- Loader HTML -->
<div id="preloader" class="fixed inset-0 z-[9999] bg-white">
    <div class="loader-content absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
        <!-- Logo Container -->
        <div class="logo-container mb-6">
            <div class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                SoftDomi System
            </div>
            <div class="text-sm text-gray-500 mt-1">Version 1.0</div>
        </div>
        
        <!-- Animated Loader -->
        <div class="loader-ring">
            <div class="spinner"></div>
            <div class="percentage" id="loading-percentage">0%</div>
        </div>
        
        <!-- Loading Text -->
        <div class="loading-text mt-4 text-gray-600">
            <span class="dot-animation">Loading System</span>
        </div>
    </div>
</div>
<body>
<style>
#preloader {
    transition: all 0.4s ease-in-out;
}

#preloader.loaded {
    opacity: 0;
    visibility: hidden;
}

/* Spinner Animation */
.loader-ring {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.spinner {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top: 3px solid #3b82f6;
    border-right: 3px solid #3b82f6;
    border-radius: 50%;
    animation: spinLoader 0.8s linear infinite;
}

.percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 16px;
    font-weight: 600;
    color: #3b82f6;
}

@keyframes spinLoader {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Dot Animation */
.dot-animation::after {
    content: '';
    animation: dots 1.5s infinite;
}

@keyframes dots {
    0%, 20% { content: ''; }
    40% { content: '.'; }
    60% { content: '..'; }
    80%, 100% { content: '...'; }
}

/* Logo Animation */
.logo-container {
    animation: fadeInDown 0.6s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

</style>

<script>
// loader-script.js
document.addEventListener('DOMContentLoaded', function() {
    const preloader = document.getElementById('preloader');
    const percentage = document.getElementById('loading-percentage');
    let progress = 0;
    
    // Check last visit time
    const lastVisit = localStorage.getItem('lastLoaderVisit');
    const currentTime = new Date().getTime();
    const fiveHoursInMs = 5 * 60 * 60 * 1000; // 5 hours in milliseconds
    
    // Set the speed based on time difference
    let speed;
    if (!lastVisit || (currentTime - parseInt(lastVisit)) > fiveHoursInMs) {
        speed = 250; // Slower speed for first visit or after 5 hours
    } else {
        speed = 1; // Faster speed for frequent visits
    }
    
    // Store current visit time
    localStorage.setItem('lastLoaderVisit', currentTime.toString());

    const updateLoader = () => {
        if (progress < 100) {
            progress += Math.floor(Math.random() * 15) + 10; // Bigger jumps
            if (progress > 100) progress = 100;
            percentage.textContent = `${progress}%`;

            if (progress === 100) {
                setTimeout(() => {
                    preloader.classList.add('loaded');
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 400);
                }, 200);
            } else {
                setTimeout(updateLoader, speed);
            }
        }
    };

    // Start the loader
    setTimeout(updateLoader, 100);

    // Fallback - hide loader after maximum time (adjusted based on speed)
    const maxLoadTime = speed === 140 ? 3000 : 1000; // Longer timeout for slower speed
    setTimeout(() => {
        if (!preloader.classList.contains('loaded')) {
            progress = 100;
            percentage.textContent = '100%';
            preloader.classList.add('loaded');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 400);
        }
    }, maxLoadTime);
});

// Optional: Function to manually reset the last visit time (for testing)
function resetLoaderTimer() {
    localStorage.removeItem('lastLoaderVisit');
}
</script>
</body>
</htmL>