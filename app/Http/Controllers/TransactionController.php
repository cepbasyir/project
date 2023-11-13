<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Requests\TransactionStore;
use App\Models\Book;
use App\Models\Member;
use App\Models\TransactionDetail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;


class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     */

    public function index()
    {

        // $transactions = Transaction::all(); tdk di gunakan krn menggunakan api
        // return view('admin.transaction.index', compact('transactions'));

        return view('admin.transaction.index');
    }

    public function api(Request $request)
    {
        if ($request->status) {
            $transactions = Transaction::with(['transactionDetails.book', 'member'])
            ->where('status', '=', $request->status == 2 ? 0 : 1)
            ->get();
        }
         else if ($request->date_start) { 
            $transactions = Transaction::with(['transactionDetails.book', 'member'])
            ->where('date_start', '>=', $request->date_start)
            ->get();
        } 
        else {
            $transactions = Transaction::with(['transactionDetails.book', 'member'])->get();
        }

        $datatables = datatables()->of($transactions)->addIndexColumn()
            ->addColumn('name', function ($transaction) {
                return $transaction->member->name;
            })
            ->addColumn('duration', function ($transaction) {
                return dateDiff($transaction->date_start, $transaction->date_end) . " Days";
            })
            ->addColumn('total', function ($transaction) {
                return $transaction->transactionDetails->count();
            })
            ->addColumn('purchase', function ($transaction) {
                $purchases = $transaction->transactionDetails->sum('book.price');
                return "Rp. " . number_format($purchases);
            })
            ->addColumn('status', function ($transaction) {
                return $transaction->status ? "Has been returned" : "Not returned yet";
            });
            // ->addIndexColumn();

        // $transaction = Transaction::all();
        // $datatables = datatables()->of($transaction)->addIndexColumn();

            return $datatables->make(true);
        }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // $members = Member::all();
        // $books = Book::where('qty', '>=', 1)->get();

        // return view('admin.transaction.create', compact('members', 'books'));

        $members = Member::all();
        $transaction = Transaction::with('member', 'details.books');

        $books = Book::select('books.id', 'books.title')
            ->join('transaction_details', 'transaction_details.book_id', '=', 'books.id')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('members', 'members.id', '=', 'transactions.member_id')
            ->groupBy('books.id', 'books.title')
            ->orderBy('books.title')
            ->get();
        return view('admin.transaction.create', compact('members', 'books'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $transaction = Transaction::create([
            'member_id' => $request->member_id,
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'book' => $request->book,
            

        ]);

        if ($transaction) {
            // $books = $request->book;
            foreach ($request->book_id as $book) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'book_id' => $book,
                    'qty' => 1
                ]);

                 // update Books Stock
                 $books = Book::find($book);
                 $books->qty -= 1;
                 $books->save();
            }
        }

        return redirect('transactions');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        $books = Book::where('qty', '>=', 1)->get();
        $transactionDetails = TransactionDetail::where('transaction_id', $transaction->id)->get();

        // return $transaction->member->id;
        return view('admin.transaction.detail', compact('transaction', 'books', 'transactionDetails'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        $members = Member::all();
        $books = Book::where('qty', '>=', 1)->get();
        $transactionDetails = TransactionDetail::where('transaction_id', $transaction->id)->get();


        return view('admin.transaction.edit', compact('members', 'books', 'transaction', 'transactionDetails'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $transactions = Transaction::find($transaction->id)
            ->update([
                'member_id' => $request->member_id,
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
                'status' => $request->status,
            ]);

        if ($transaction) {
            // Delete all matched transaction Detail
            TransactionDetail::where('transaction_id', $transaction->id)->delete();
             // Insert new Transaction Details data into database
             foreach ($request->book_id as $book) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'book_id' => $book,
                    'qty' => 1,
                    'status' => 1,
                ]);

                // Update Books Stock
                $books = Book::find($book);
                if ($request->status == 1) { // if the book has Returned increment the book stock
                    $books->qty += 1;
                }
                $books->update();
             }
        }

        return redirect('transactions')->with('success', 'Transaction data has been Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        //
    }
}
