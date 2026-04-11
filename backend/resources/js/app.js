import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('readerToast');
    let toastTimer = null;

    const showToast = (message) => {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.classList.add('is-visible');

        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }

        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2400);
    };

    document.querySelectorAll('[data-share-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const shareUrl = button.getAttribute('data-share-url');
            const shareTitle = button.getAttribute('data-share-title') || 'Story preview';

            if (!shareUrl) {
                return;
            }

            try {
                if (navigator.share) {
                    await navigator.share({
                        title: shareTitle,
                        url: shareUrl,
                    });
                    return;
                }

                await navigator.clipboard.writeText(shareUrl);
                showToast('Story preview link copied to clipboard.');
            } catch (error) {
                showToast('Unable to share right now. Try again in a moment.');
            }
        });
    });
});
