function ajaxFetchShippingCompaniesJs(ajaxUrl) {
    const countrySelect = document.getElementById('country');
    const companySelect = document.getElementById('company');

    countrySelect.addEventListener('change', function() {
        const country = this.value;
        companySelect.innerHTML = '<option value="all">Todo el país</option>';
        if (!country) return;

        fetch(ajaxUrl + '?action=fetch_shipping_companies&pais=' + country)
            .then(res => res.json())
            .then(({ success, data }) => {
                if (success && data.empresas.length > 0) {
                    data.empresas.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company;
                        option.textContent = company;
                        companySelect.appendChild(option);
                    });
                }
            });
    });
}