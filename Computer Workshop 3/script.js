const showBtn = document.getElementById('showFormBtn');
const formContainer = document.querySelector('.ticket-form-container');
const ticketForm = document.querySelector('form'); 
const descInput = document.querySelector('textarea[name="description"]');

showBtn.addEventListener('click', () => {
    formContainer.style.display = 'block';
    showBtn.style.display = 'none';
});

descInput.addEventListener('input', () => {
    localStorage.setItem('ticketDraft', descInput.value);
});

document.addEventListener('DOMContentLoaded', () => {
    const savedDraft = localStorage.getItem('ticketDraft');
    if (savedDraft) {
        descInput.value = savedDraft;
    }
});

ticketForm.addEventListener('submit', (event) => {
    const successMsg = document.getElementById('success-msg');
    const errorMsg = document.getElementById('js-error-msg');
    
    if (successMsg) {
        successMsg.style.display = 'none';
    }
    
    errorMsg.style.display = 'none';
    
    if (descInput.value.length < 15) {
        event.preventDefault();
        errorMsg.textContent = 'Опис має бути не менше 15 символів!';
        errorMsg.style.display = 'block';
        return;
    }

    localStorage.removeItem('ticketDraft');
});

document.getElementById('closeFormBtn').addEventListener('click', () => {
    formContainer.style.display = 'none';
    showBtn.style.display = 'block';
});