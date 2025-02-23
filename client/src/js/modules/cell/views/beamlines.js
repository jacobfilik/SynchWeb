define(['marionette', 'utils',
        'jquery',
        'jquery.flot',
        'jquery.flot.stack',
], function(Marionette, utils, $) {
       

    return Marionette.ItemView.extend({
        template: false,
        modelEvents: { 'change': 'render' },

        onRender: function() {
              if (this.model.get('data')) {
                  var did = this.getOption('second') ? 1 : 0
                  var ticks = []
                
                var years = {}
                var bls = {}
                _.each(this.model.get('data')[did], function(v, i) {
                    years[v.YEAR] = 1
                    bls[v.BL] = 1
                })

                var d = []
                var yrs = []

                _.each(years, function(i, y) {
                    yrs.push(y)
                    d.push({ label: y, data: [] })
                })

                var bl = []
                var data = {}
                _.each(bls, function(i, b) {
                    data[b] = {}
                    bl.push(b)
                    ticks.push([bl.length-1, b])
                    _.each(years, function(x, y) {
                        data[b][y] = 0
                    })
                })

                _.each(this.model.get('data')[did], function(v, i) {
                    data[v.BL][v.YEAR] = v.COUNT
                })

                _.each(data, function(el, beamline) {
                    _.each(el, function(count, year) {
                        var y = yrs.indexOf(year)
                        var b = bl.indexOf(beamline)
                        d[y].data.push([b, count])
                    })
                })

                var options = {
                    series: {
                        bars: {
                            show: true,
                            barWidth: .9,
                            align: 'center'
                        },
                        stack: true
                    },
                    grid: {
                        hoverable: true,
                        borderWidth: 0,
                    },
                    xaxis: {
                       ticks: ticks
                    },
                }

                this.plot = $.plot(this.$el, d, options)
                this.$el.css('opacity', 1)
              }
      },
        
      onDestroy: function() {
          if (this.plot) this.plot.shutdown()
      }

      })

})
