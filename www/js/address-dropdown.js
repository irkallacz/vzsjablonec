function addOptions(el, data) {
    el.innerHTML = '';
    for (const item of data) {
        let newOptionElement = document.createElement('option');
        newOptionElement.textContent = item;
        el.appendChild(newOptionElement);
    }
}

function setCityList() {
    let input = document.getElementById('city')
    if (input.value.length >= 3) {
        let list = document.getElementById('city-list');
        const request = new Request(input.dataset.url + '?city=' + input.value)
        fetch(request)
            .then(response => response.json())
            .then(data => {
                if (data.length >= 1) {
                    addOptions(list, data);
                }
            })
            .catch(console.error);
        input.blur();
        input.focus();
    }
}

function setStreetList() {
    let input = document.getElementById('street');

    const street = input.value;
    const city = document.getElementById('city').value;

    if ((city.length)&&((street.length === 0)||(street.length >= 3))) {
        let list = document.getElementById('street-list');
        let params = `?city=${city}`;
        if (street.length) {
            params = params + `&street=${street}`;
        }
        const request = new Request(input.dataset.url + params)
        fetch(request)
            .then(response => response.json())
            .then(data => {
                if (data.length >= 1) {
                    addOptions(list, data);
                }
            })
            .catch(console.error);
        input.blur();
        input.focus();
    }
}

function setPostalCodeList() {
    const street = document.getElementById('street').value;
    const city = document.getElementById('city').value;
    let input = document.getElementById('postal-code');
    if ((city.length)&&(street.length)) {
        let list = document.getElementById('postal-code-list');
        list.innerHTML = '';
        const request = new Request(input.dataset.url + '?city=' + city + '&street=' + street)
        fetch(request)
            .then(response => response.json())
            .then(data => {
                if (data.length == 1) {
                    input.value = data[0];
                }
                if (data.length > 1) {
                    addOptions(list, data);
                }
            })
            .catch(console.error);
    }
}

document.getElementById('city').addEventListener('input', setCityList);
document.getElementById('street').addEventListener('input', setStreetList);
document.getElementById('postal-code').addEventListener('focus', setPostalCodeList);
