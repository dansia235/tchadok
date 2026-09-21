document.addEventListener('DOMContentLoaded', function () {
    var counters = document.querySelectorAll('[data-count]');
    if (!counters.length) {
        return;
    }

    function animateCounter(counter) {
        var target = parseInt(counter.getAttribute('data-count'), 10);
        if (Number.isNaN(target)) {
            return;
        }

        var duration = 1600;
        var start = null;

        function step(timestamp) {
            if (!start) {
                start = timestamp;
            }
            var progress = Math.min((timestamp - start) / duration, 1);
            var value = Math.floor(progress * target);
            counter.textContent = value.toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                counter.textContent = target.toLocaleString();
            }
        }

        requestAnimationFrame(step);
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.4 });

    counters.forEach(function (counter) {
        counter.textContent = '0';
        observer.observe(counter);
    });
});
