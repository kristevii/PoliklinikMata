'use strict';

document.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    floatchart();
  }, 500);
});

function floatchart() {
  (function () {
    // Data dari window global (didefinisikan di dashboard.php)
    var dataBaru = window.chartBaru || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    var dataKontrol = window.chartKontrol || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    var bulanLabels = window.chartBulan || ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var dataHarian = window.dataHarian || [10, 15, 12, 18, 20, 25, 22];
    var dataAntrian12Bulan = window.dataAntrian12Bulan || [5, 8, 12, 15, 20, 25, 30, 28, 35, 40, 38, 42];
    
    var options1 = {
      chart: { type: 'bar', height: 50, sparkline: { enabled: true } },
      colors: ['#4680FF'],
      plotOptions: { bar: { columnWidth: '80%' } },
      series: [{ data: dataBaru }],
      tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function (seriesName) { return ''; } } } }
    };
    var chart = new ApexCharts(document.querySelector('#all-earnings-graph'), options1);
    chart.render();
    
    var options2 = {
      chart: { type: 'bar', height: 50, sparkline: { enabled: true } },
      colors: ['#E58A00'],
      plotOptions: { bar: { columnWidth: '80%' } },
      series: [{ data: dataHarian }],
      tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function (seriesName) { return ''; } } } }
    };
    var chart = new ApexCharts(document.querySelector('#page-views-graph'), options2);
    chart.render();
    
    var options3 = {
      chart: { type: 'bar', height: 50, sparkline: { enabled: true } },
      colors: ['#2CA87F'],
      plotOptions: { bar: { columnWidth: '80%' } },
      series: [{ data: dataAntrian12Bulan }],
      tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function (seriesName) { return ''; } } } }
    };
    var chart = new ApexCharts(document.querySelector('#total-task-graph'), options3);
    chart.render();
    
    var options4 = {
      chart: { type: 'bar', height: 50, sparkline: { enabled: true } },
      colors: ['#DC2626'],
      plotOptions: { bar: { columnWidth: '80%' } },
      series: [{ data: dataKontrol }],
      tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function (seriesName) { return ''; } } } }
    };
    var chart = new ApexCharts(document.querySelector('#download-graph'), options4);
    chart.render();
    
    var options5 = {
      chart: {
        fontFamily: 'Inter var, sans-serif',
        type: 'area',
        height: 370,
        toolbar: {
          show: false
        }
      },
      colors: ['#0d6efd', '#8996A4'],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          type: 'vertical',
          inverseColors: false,
          opacityFrom: 0.2,
          opacityTo: 0
        }
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        width: 3
      },
      plotOptions: {
        bar: {
          columnWidth: '45%',
          borderRadius: 4
        }
      },
      grid: {
        show: true,
        borderColor: '#F3F5F7',
        strokeDashArray: 2
      },
      series: [
        {
          name: 'Kunjungan Baru',
          data: dataBaru
        },
        {
          name: 'Kunjungan Kontrol',
          data: dataKontrol
        }
      ],
      xaxis: {
        categories: bulanLabels,
        axisBorder: {
          show: false
        },
        axisTicks: {
          show: false
        }
      },
      tooltip: {
        y: {
          formatter: function(val) {
            return val + ' pasien';
          }
        }
      }
    };
    var chart = new ApexCharts(document.querySelector('#customer-rate-graph'), options5);
    chart.render();
    
    var options6 = {
      chart: {
        type: 'area',
        height: 60,
        stacked: true,
        sparkline: { enabled: true }
      },
      colors: ['#4680FF'],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          type: 'vertical',
          inverseColors: false,
          opacityFrom: 0.5,
          opacityTo: 0
        }
      },
      stroke: { curve: 'smooth', width: 2 },
      series: [{ data: dataBaru }]
    };
    var chart = new ApexCharts(document.querySelector('#total-tasks-graph'), options6);
    chart.render();
    
    var options7 = {
      chart: {
        type: 'area',
        height: 60,
        stacked: true,
        sparkline: { enabled: true }
      },
      colors: ['#DC2626'],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          type: 'vertical',
          inverseColors: false,
          opacityFrom: 0.5,
          opacityTo: 0
        }
      },
      stroke: { curve: 'smooth', width: 2 },
      series: [{ data: dataAntrian12Bulan }]
    };
    var chart = new ApexCharts(document.querySelector('#pending-tasks-graph'), options7);
    chart.render();
  })();
}