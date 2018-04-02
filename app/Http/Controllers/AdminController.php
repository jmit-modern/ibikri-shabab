<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Session;
use Illuminate\Support\Facades\Redirect;
use App\Http\Middleware\CheckAdmin;
use Illuminate\Support\Facades\Input;
use DataTables;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Division;
use App\Models\City;
use App\Models\Post;

session_start();

class AdminController extends Controller {

    //Layout holder
    private $layout;

    //Construct Common Items and Check Auth
    public function __construct() {
        $this->middleware(CheckAdmin::class);

        $this->layout['adminNotification'] = view('admin.common.notification');
    }

    /**
     * Show dashboard
     * @return type
     */
    public function index() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.dashboard');

        //return view
        return view('admin.master', $this->layout);
    }

    public function adsDatatable() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.ads.datatable');

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * datatables/getdata handler
     */
    public function adsDatatableGetData() {

        $posts = Post::select(['posts.post_id', 'users.name', 'posts.ad_title', 'cities.city_title_en', 'subcategories.subcategory_title_en', 'posts.short_description','posts.status', 'posts.created_at'])
                ->join('subcategories', 'subcategories.subcategory_id', '=', 'posts.subcategory_id')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->join('cities', 'cities.city_id', '=', 'users.city_id');


        return \DataTables::of($posts)
                        ->editColumn('status', function($row) {
                            
                            $status = 'something wrong';
                            if($row->status == 1){
                                $status = '<span class="label label-success">Published</span>';
                            }                            
                            elseif($row->status == 0){
                                $status = '<span class="label label-warning">Unpublished</span>';
                            }
                            return $status;
                        })
                        ->addColumn('actions', function($row) {
                            $buttons = "";
                            
                            if($row->status == 1){
                                $buttons .= "<a class='btn btn-xs btn-warning dtbutton' href='#' data-href='" . url('admin/ads/changeStatus/unpublish') . "/$row->post_id'><i class='fa fa-thumbs-down'></i></a>";
                            }                            
                            elseif($row->status == 0){
                                $buttons .= "<a class='btn btn-xs btn-success dtbutton' href='#' data-href='" . url('admin/ads/changeStatus/publish') . "/$row->post_id'><i class='fa fa-thumbs-up'></i></a>";
                            }                            
                            
                            $buttons .= "<a class='btn btn-xs btn-danger  dtbutton' href='#' data-href='" . url('admin/ads/changeStatus/delete') . "/$row->post_id'><i class='fa fa-times'></i></a>";

                            return "<div class='btn-group'>$buttons</div>";
                        })
                        ->rawColumns(['actions', 'status'])
                        ->make(true);
    }

    public function adsChangeStatus($status, $id) {

        $post = Post::find($id);

        switch ($status) {
            case "publish":
                $post->status = 1;
                $post->save();
                break;
            case "unpublish":
                $post->status = 0;
                $post->save();
                break;
            case "delete":
                foreach ($post->postimages as $aPostImage) {
                    //remove images
                    $image = base_path("public/$aPostImage->postimage_file");
                    $thumbnail = base_path("public/$aPostImage->postimage_thumbnail");

                    unlink($image);
                    unlink($thumbnail);
                }
                $post->delete();
                break;
            default:
                break;
        }
        
        return Redirect::to('admin/ads');
    }

    /**
     * Category Management Start
     */

    /**
     * List Category
     * @return type
     */
    public function categoryView() {

        $categories = Category::orderBy('category_weight', 'ASC')->get();

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.category.list')
                ->with('categories', $categories);

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Edit Category Form
     * @param type $id
     * @return type
     */
    public function categoryEdit($id) {

        $oldCategoryData = Category::find($id);

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.category.categorycreate')
                ->with('oldCategoryData', $oldCategoryData);

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Create Category Form
     * @return type
     */
    public function categoryCreate() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.category.categorycreate');

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Save Category POST handler
     * @param Request $request
     * @return type
     */
    public function categorySaveCategory(Request $request) {


        $redirectUrl = '/admin/categories';

        if (isset($request->category_id)) {

            $redirectUrl = '/admin/category/edit/' . $request->category_id;

            $category = Category::find($request->category_id);

            Session::put('message', array(
                'title' => 'Category Updated',
                'body' => "Category Info Updated",
                'type' => 'info'
            ));
        } else {

            $validatedData = $request->validate([
                'category_title_en' => 'required|string|unique:categories|max:50',
                'category_title_bn' => 'required|string|unique:categories|max:50',
                'category_image' => 'required',
                'category_icon' => 'required'
            ]);

            $category = new Category;

            Session::put('message', array(
                'title' => 'Category Created',
                'body' => "Created New Category",
                'type' => 'success'
            ));

            $category->category_image = "";
        }



        $category->category_title_en = $request->category_title_en;
        $category->category_title_bn = $request->category_title_bn;

        $category->category_icon = $request->category_icon;

        if ($request->has('categor_weight')) {
            $category->category_weight = $request->category_weight;
        } else {
            $category->category_weight = 0;
        }
        $category->category_caption = $request->category_caption;


        /*
         * Image Upload
         */
        $files = $request->file('category_image');

        //File Is Selected, Proceed with upload
        if ($files) {

            $extension = $files->extension();

            $allowedExtensions = ['png'];

            if (!( $request->file('category_image')->isValid() && (in_array($extension, $allowedExtensions)) )) {

                //File Upload Failed, 
                Session::put('message', array(
                    'title' => 'Invalid File Selected',
                    'body' => "Please select image file with png extension. With less than 10kb size",
                    'type' => 'danger'
                ));

                return Redirect::to($redirectUrl);
            }

            $filename = $files->getClientOriginalName();
            $customName = str_replace(' ', '_', strtolower($request->category_title_en)) . "." . $extension;
            $imgUrl = 'images/category/' . $customName;
            $destinationPath = base_path() . "/public/images/category/";

            //Try upload
            $success = $files->move($destinationPath, $customName);

            if ($success) {

                //Delete Old iMage if edit and has old image
                if (isset($request->category_id) && ($request->category_image_old != "")) {
                    $oldFileName = $request->category_image_old;
                    unlink($oldFileName);
                }

                $category->category_image = $imgUrl;

                //If it is an edit , remove old file
            } else {

                //File Upload Failed, 
                Session::put('message', array(
                    'title' => 'Error',
                    'body' => "File Upload Failed",
                    'type' => 'danger'
                ));
            }
        }

        $category->save();

        return Redirect::to($redirectUrl);
    }

    /**
     * Sub Category Edit Form
     * @param type $subcategory_id
     * @return type
     */
    public function subcategoryEdit($subcategory_id) {

        $oldCategoryData = Subcategory::find($subcategory_id);

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.subcategory.form')
                ->with('oldCategoryData', $oldCategoryData);

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Sub Category Create Form
     * @return type
     */
    public function subcategoryCreate() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.subcategory.form');

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Sub Category Save POST handler
     * @param Request $request
     * @return type
     */
    public function subcategorySave(Request $request) {

        $redirectUrl = '/admin/subcategory/create';

        if (isset($request->subcategory_id)) {

            $redirectUrl = '/admin/subcategory/edit/' . $request->subcategory_id;

            $subcat = Subcategory::find($request->subcategory_id);

            Session::put('message', array(
                'title' => 'Sub Category Updated',
                'body' => "Sub Category Info Updated",
                'type' => 'info'
            ));


            $validatedData = $request->validate([
                'parent_category_id' => 'required',
                'subcategory_title_en' => 'required|string',
                'subcategory_title_bn' => 'required|string'
            ]);
        } else {


            $validatedData = $request->validate([
                'parent_category_id' => 'required',
                'subcategory_title_en' => 'required|string|unique:subcategories|max:50',
                'subcategory_title_bn' => 'required|string|unique:subcategories|max:50'
            ]);


            $subcat = new Subcategory;

            Session::put('message', array(
                'title' => 'Sub Category Created',
                'body' => "Created New Sub Category $request->subcategory_title_en ($request->subcategory_title_bn)",
                'type' => 'success'
            ));
        }


        $subcat->parent_category_id = $request->parent_category_id;
        $subcat->subcategory_title_en = $request->subcategory_title_en;
        $subcat->subcategory_title_bn = $request->subcategory_title_bn;

        if ($request->has('subcategory_weight')) {
            $subcat->subcategory_weight = $request->subcategory_weight;
        } else {
            $subcat->subcategory_weight = 0;
        }
        $subcat->subcategory_caption = $request->subcategory_caption;

        $subcat->save();

        return Redirect::to($redirectUrl);
    }

    /**
     * Category Management End
     */
    /**
     * Location Management Start
     */

    /**
     * List Locations
     * @return type
     */
    public function locationView() {

        $divisions = Division::orderBy('division_weight', 'ASC')->get();

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.location.list')
                ->with('divisions', $divisions);

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Show Create Division FOrm
     * @return type
     */
    public function divisionCreate() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.location.divisionform');

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Show Division Edit Form
     * @param type $id
     * @return type
     */
    public function divisionEdit($id) {

        $oldDivisionData = Division::find($id);

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.location.divisionform')
                ->with('oldDivisionData', $oldDivisionData);

        //return view
        return view('admin.master', $this->layout);
    }

    /**
     * Save Division Data, POST handler
     * @param Request $request
     * @return type
     */
    public function divisionSave(Request $request) {


        $redirectUrl = '/admin/division/create';

        if (isset($request->division_id)) {

            $redirectUrl = '/admin/division/edit/' . $request->division_id;

            $validatedData = $request->validate([
                'division_title_en' => 'required|string',
                'division_title_bn' => 'required|string'
            ]);

            $division = Division::find($request->division_id);

            Session::put('message', array(
                'title' => 'Division Updated',
                'body' => "Division Info Updated",
                'type' => 'info'
            ));
        } else {

            $validatedData = $request->validate([
                'division_title_en' => 'required|string|unique:divisions|max:50',
                'division_title_bn' => 'required|string|unique:divisions|max:50'
            ]);

            $division = new Division;

            Session::put('message', array(
                'title' => 'Division Created',
                'body' => "Created New Division $request->division_title_en ($request->division_title_bn)",
                'type' => 'success'
            ));
        }

        $division->division_title_en = $request->division_title_en;
        $division->division_title_bn = $request->division_title_bn;
        $division->division_weight = $request->division_weight;
        $division->division_icon = $request->division_icon;

        $division->save();

        return Redirect::to($redirectUrl);
    }

    /**
     * Show Create City Form
     * @return type
     */
    public function cityCreate() {

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.location.cityform');

        //return view
        return view('admin.master', $this->layout);
    }

    public function cityEdit($id) {

        $oldCityData = City::find($id);

        //Load Component
        $this->layout['adminContent'] = view('admin.partials.location.cityform')
                ->with('oldCityData', $oldCityData);

        //return view
        return view('admin.master', $this->layout);
    }

    public function citySave(Request $request) {


        $redirectUrl = '/admin/city/create';

        if (isset($request->city_id)) {

            $redirectUrl = '/admin/city/edit/' . $request->city_id;

            $validatedData = $request->validate([
                'city_title_en' => 'required|string',
                'city_title_bn' => 'required|string'
            ]);

            $city = City::find($request->city_id);

            Session::put('message', array(
                'title' => 'City Updated',
                'body' => "City Info Updated",
                'type' => 'info'
            ));
        } else {

            $validatedData = $request->validate([
                'city_title_en' => 'required|string|unique:cities|max:50',
                'city_title_bn' => 'required|string|unique:cities|max:50'
            ]);

            $city = new City;

            Session::put('message', array(
                'title' => 'City Created',
                'body' => "Created New City $request->city_title_en ($request->city_title_bn)",
                'type' => 'success'
            ));

            $redirectUrl = '/admin/city/create?division_id=' . $request->division_id;
        }

        $city->city_title_en = $request->city_title_en;
        $city->city_title_bn = $request->city_title_bn;
        $city->city_weight = $request->city_weight;

        $city->division_id = $request->division_id;

        $city->save();

        return Redirect::to($redirectUrl);
    }

    /**
     * Location Management End
     */
    /*
     * Sample page with a table
     */

    public function table() {


        //Load Component
        //Load Component
        $this->layout['adminContent'] = view('admin.partials.tables');

        //return view
        return view('admin.master', $this->layout);
    }

    public function form() {


        //Load Component
        //Load Component
        $this->layout['adminContent'] = view('admin.partials.form');

        //return view
        return view('admin.master', $this->layout);
    }

    public function logout() {


        //Admin informations
        Session::put('admin_id', 0);


        Session::forget('admin_username');
        Session::forget('admin_name');
        Session::forget('admin_privilage');

        //Message for Notification Builder
        Session::put('message', array(
            'title' => 'Logged Out, ',
            'body' => 'You are no longer logged in',
            'type' => 'warning'
        ));

        return Redirect::to('/')->send();
    }

}
