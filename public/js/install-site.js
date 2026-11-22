document.getElementById('install-site')?.addEventListener('submit', (event) => {
    const button = event.currentTarget.querySelector('button');
    button.disabled = true;
    button.textContent = 'Installing?';
});
