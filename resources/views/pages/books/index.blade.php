<x-master-layout>
    <section class="w-full py-16 bg-white">
        <div class="px-10 mx-auto max-w-7xl">

            <div class="text-center">
                <h2 class="relative inline-block px-5 py-2 mb-5 text-5xl font-bold font-extrabold bg-white border-2 border-black">

                    <div class="absolute w-full py-2 h-full inset-0 border-2 border-black bg-black ml-1.5 mt-1.5"></div>
                    <div class="absolute inset-0 w-full h-full py-2 bg-white"></div>
                    <span class="relative">From the Blog</span>
                </h2>
                <p class="text-xl font-medium text-gray-800 mb-7">View the latest posts from our blog</p>
            </div>
            <div class="grid grid-cols-12 gap-8">
                @forelse($books as $book)
                    <div
                            class="relative col-span-12 duration-150 ease-out transform border-2 border-black cursor-pointer md:col-span-6 lg:col-span-4 hover:scale-105">
                        <a href="#_" class="block h-64 overflow-hidden">
                            <img src="{{ $book->image_url }}"
                                 class="object-cover w-full h-full">
                        </a>
                        <div class="p-5 pb-6">
                            <h2 class="mb-2">
                                <a href="/extracting-tailwindcss-from-html"
                                   class="text-2xl font-bold leading-tight tracking-tight">
                                    {{ $book->name }}
                                </a>
                            </h2>
                            <p class="mb-2 text-sm font-medium tracking-widest text-gray-500">
                                <span>By {{ $book->writer->name }}</span>
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="col-span-12">
                        <p class="text-center">No books found.</p>
                    </div>
                @endforelse

                {{ $books->links() }}
            </div>
        </div>
    </section>

</x-master-layout>
