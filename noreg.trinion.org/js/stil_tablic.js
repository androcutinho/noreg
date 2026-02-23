
const tableColumnStyles = {
    
    init: function() {
        const style = document.createElement('style');
        style.textContent = `
            
            #tablitsaTovarov {
                table-layout: fixed;
                width: 100%;
            }
            
           
            .col-num {
                width: 40px;
            }
            
            .col-tovar {
                width: 450px;
            }
            
            .col-seria {
                width: 150px;
            }
            
            .col-edinitsa {
                width: 50px;
            }
            
            .col-kolichestvo {
                width: 70px;
            }
            
            .col-cena {
                width: 100px;
            }
            
            .col-ed-cena {
                width: 100px;
            }
            
            .col-nds {
                width: 50px;
            }
            
            .col-summa-stavka {
                width: 100px;
            }
            
            .col-summa {
                width: 100px;
            }
            
            .col-sklad {
                width: 120px;
            }
            
            .col-delivery-date {
                width: 120px;
            }
            
            .col-action {
                width: 40px;
                text-align: center;
            }
            
           
            #tablitsaTovarov input[type="text"],
            #tablitsaTovarov input[type="date"],
            #tablitsaTovarov select {
                width: 100%;
                box-sizing: border-box;
            }
        `;
        document.head.appendChild(style);
    }
};


if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tableColumnStyles.init);
} else {
    tableColumnStyles.init();
}
