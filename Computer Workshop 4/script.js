const showBtn = document.getElementById('showFormBtn');
const formContainer = document.querySelector('.ticket-form-container');
const ticketForm = document.querySelector('.ticket-form-container form'); 
const descInput = document.querySelector('.ticket-form-container textarea[name="description"]');

if (showBtn && formContainer) {
    showBtn.addEventListener('click', () => {
        formContainer.style.display = 'block';
        showBtn.style.display = 'none';
    });
}

if (descInput) {
    descInput.addEventListener('input', () => {
        localStorage.setItem('ticketDraft', descInput.value);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const savedDraft = localStorage.getItem('ticketDraft');
        if (savedDraft) {
            descInput.value = savedDraft;
        }
    });
}

if (ticketForm && descInput) {
    ticketForm.addEventListener('submit', (event) => {
        const successMsg = document.getElementById('success-msg');
        const errorMsg = document.getElementById('js-error-msg');
        
        if (successMsg) {
            successMsg.style.display = 'none';
        }
        
        if (errorMsg) {
            errorMsg.style.display = 'none';
        }
        
        if (descInput.value.length < 15) {
            event.preventDefault();
            if (errorMsg) {
                errorMsg.textContent = 'Опис має бути не менше 15 символів!';
                errorMsg.style.display = 'block';
            }
            return;
        }

        localStorage.removeItem('ticketDraft');
    });
}

const closeFormBtn = document.getElementById('closeFormBtn');
if (closeFormBtn && formContainer && showBtn) {
    closeFormBtn.addEventListener('click', () => {
        formContainer.style.display = 'none';
        showBtn.style.display = 'flex';
    });
}