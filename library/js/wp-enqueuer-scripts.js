if (window.jQuery) {
  jQuery(document).ready(function(){
    //jQuery('.wp_enqueuer_post_types').footable();
    var responsiveHelper = undefined;
    var breakpointDefinition = {
        tablet: 1024,
        phone : 480
    };
    var tableElement = jQuery('.wp_enqueuer_post_types');
    tableElement.dataTable({
      "sPaginationType": "full_numbers",
      //disable sorting on first column
      "aoColumnDefs" : [ 
        {
          'bSortable' : false,
          'aTargets' : [ 0 ]
        },
        {
          'asSorting': [ 'asc' ],
          'aTargets': [ 1 ]
        }
      ],
      bAutoWidth     : false,
      fnPreDrawCallback: function () {
          // Initialize the responsive datatables helper once.
          if (!responsiveHelper) {
              responsiveHelper = new ResponsiveDatatablesHelper(tableElement, breakpointDefinition);
          }
      },
      fnRowCallback  : function (nRow) {
          responsiveHelper.createExpandIcon(nRow);
      },
      fnDrawCallback : function (oSettings) {
          responsiveHelper.respond();
      }
    });
    //sort by the name field
    tableElement.fnSort( [ [1,'asc'] ] );
  });
}