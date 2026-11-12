/*
 * Informe de Envíos: fills the courier select with the couriers of the chosen country.
 * From WPCodeBox › Shipping Report › Fetch (snippet 50). Loaded by
 * includes/shipping-report/ui.php.
 *
 * 2026-11-12: while the couriers load, the select is marked aria-busy (admin-controls.js shows
 * it as "Cargando…"); without a country it stays disabled; a failed request says so instead of
 * leaving the list empty; an older answer that arrives after a newer one is ignored.
 */
function ajaxFetchShippingCompaniesJs(ajaxUrl) {
    const countrySelect = document.getElementById('country');
    const companySelect = document.getElementById('company');
    let request = 0;

    function setOptions(list) {
        companySelect.textContent = '';
        list.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item[0];
            option.textContent = item[1];
            companySelect.appendChild(option);
        });
    }

    countrySelect.addEventListener('change', function () {
        const country = this.value;
        const current = ++request;

        if (!country) {
            companySelect.removeAttribute('aria-busy');
            setOptions([['', 'Elige primero un país']]);
            companySelect.disabled = true;
            return;
        }

        companySelect.disabled = true;
        companySelect.setAttribute('aria-busy', 'true');

        fetch(ajaxUrl + '?action=fetch_shipping_companies&pais=' + encodeURIComponent(country), { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (result) {
                if (current !== request) {
                    return;
                }
                const companies = result && result.success && result.data && result.data.empresas ? result.data.empresas : [];
                setOptions([['all', 'Todo el país']].concat(companies.map(function (company) {
                    return [company, company];
                })));
                companySelect.disabled = false;
            })
            .catch(function (error) {
                if (current !== request) {
                    return;
                }
                console.error('Informe de Envíos: no se pudieron cargar las empresas', error);
                // Empty value: "required" stops the form until the country is picked again.
                setOptions([['', 'No se pudieron cargar. Elige el país de nuevo.']]);
                companySelect.disabled = false;
            })
            .finally(function () {
                if (current === request) {
                    companySelect.removeAttribute('aria-busy');
                }
            });
    });
}
