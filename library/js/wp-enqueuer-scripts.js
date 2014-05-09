if (window.jQuery) {
  jQuery(document).ready(function($){
    //jQuery('.wp_enqueuer_post_types').footable();
    var responsiveHelper = undefined;
    var breakpointDefinition = {
        tablet: 1024,
        phone : 480
    };
    var tableElement = $('.wp_enqueuer_post_types');
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

    //save the scripts the user enqueued when datatables pagination is enabled and the fields we selected are not on the current page
    $(document).on('click','.wp_enqueuer_save',function(){
      var data = tableElement.$('input').serializeArray();
      var append_fields;
      for( var i = 0; i < data.length; i++ ){
        if( typeof append_fields == 'undefined' ){
          append_fields = '<input type="hidden" name="'+ data[i].name + '" value="'+ data[i].value+'">'; 
        }else{
          append_fields += '<input type="hidden" name="'+ data[i].name + '" value="'+ data[i].value+'">'; 
        }
      }
      $('#wp_enqueuer_settings').append(append_fields);
    });
  });
}