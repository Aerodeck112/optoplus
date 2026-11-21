// Butonul „Tipărește” (ex. formularul tip de retragere).
export default function print(button) {
    button.addEventListener('click', () => window.print());
}
