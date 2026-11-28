/* Bilingual public QR menu — no impact on order/cart IDs, prices or payments. */
(() => {
 'use strict';
 const dictionary = {
  "بنك فلسطين": "Bank of Palestine",
/* TISH-I18N-MISSING-KEYS-V1 */
  "منتجات السلة": "Cart items",
  "السلة فارغة": "Your cart is empty",
  "تصفح المنيو": "Browse menu",
  "اختر صنفك المفضل": "Choose your favorite item",
  "بيانات الطلب": "Order details",
  "تعديل السلة": "Edit cart",
  "قد تُضاف رسوم توصيل حسب نوع الطلب.": "Delivery fees may apply depending on your order type.",
  "تظهر بعد اختيار طريقة الاستلام في الخطوة التالية.": "They will appear after you choose a service method.",
  "ملخص سلة الطلب": "Cart summary",
  "صنف في سلة الطلب": "Item in cart",
  "تقليل كمية": "Decrease quantity of",
  "زيادة كمية": "Increase quantity of",
  "اختر حساب الدفع أو التحويل": "Select a payment or transfer account",
  "السعر": "Price",
  "اختيارات مميزة من المنيو": "Featured selections from our menu",
  "الرئيسية": "Home",
  "المنيو": "Menu",
  "السلة": "Cart",
  "المفضلة": "Favorites",
  "طلباتي": "My orders",
  "سلتك": "Your cart",
  "عرض السلة": "View cart",
  "تغيير الفرع": "Change branch",
  "ثبّت المنيو": "Pin menu",
  "رجوع": "Back",
  "كل الأصناف": "All products",
  "الكل": "All",
  "التصنيفات": "Categories",
  "عرض الكل": "See all",
  "اختر اللي على بالك": "Choose what you like",
  "اختيارات اليوم": "Today's picks",
  "أهلاً بك": "Welcome",
  "تجربة ألذ تبدأ من هنا": "A delicious experience starts here",
  "ابدأ الطلب": "Start ordering",
  "شو بتحب تأكل اليوم؟": "What would you like today?",
  "مخبوزات وأصناف مميزة من المنيو": "Featured treats from our menu",
  "كل اللي بتحبه بمكان واحد": "All your favorites in one place",
  "المنيو كامل": "Full menu",
  "المنيو الكامل": "Full menu",
  "الأكثر طلباً": "Most ordered",
  "الأكثر طلباً 🔥": "Most popular 🔥",
  "مذاق استثنائي": "Exceptional taste",
  "مذاق يستحق أن تعود إليه": "A taste worth coming back for",
  "اطلب الآن": "Order now",
  "فلترة الأصناف": "Filter products",
  "تصفية الأصناف": "Filter products",
  "عرض النتائج": "Show results",
  "إعادة ضبط": "Reset",
  "نطاق السعر": "Price range",
  "الترتيب": "Sort by",
  "الافتراضي": "Default",
  "السعر: من الأقل": "Price: low to high",
  "السعر: من الأعلى": "Price: high to low",
  "أقل من 20": "Under 20",
  "أكثر من 50": "Over 50",
  "ابحث عن صنفك المفضل...": "Search your favorite items...",
  "العروض الحالية": "Current offers",
  "الصنف السابق": "Previous item",
  "الصنف التالي": "Next item",
  "صفحات الأصناف المميزة": "Featured items",
  "محضّر بعناية إلك": "Made with care",
  "متوفر": "Available",
  "غير متوفر": "Unavailable",
  "غير متوفر حالياً": "Currently unavailable",
  "اختر الخيارات": "Choose options",
  "إضافة": "Add",
  "إضافة للسلة": "Add to cart",
  "إضافة للمفضلة": "Add to favorites",
  "أضف للمفضلة": "Add to favorites",
  "إزالة من المفضلة": "Remove from favorites",
  "قد يعجبك أيضًا": "You may also like",
  "الحجم / النوع": "Size / type",
  "مطلوب": "Required",
  "اختيار مطلوب": "Selection required",
  "اختياري": "Optional",
  "اختر الحجم/النوع أولاً.": "Choose a size or type first.",
  "اختر الحجم/المتغير أولاً": "Choose a size or variant first",
  "تمت الإضافة للسلة": "Added to cart",
  "تمت الإضافة": "Added",
  "إضافة المزيد للسلة": "Add more to cart",
  "لا يوجد وصف إضافي لهذا الصنف.": "No additional description for this item.",
  "لا توجد أصناف في المفضلة.": "No favorites yet.",
  "تصفّح المنيو": "Browse menu",
  "لا توجد أصناف مطابقة.": "No matching products.",
  "لا توجد أصناف مطابقة لبحثك.": "No products match your search.",
  "لا توجد أصناف مميزة متاحة حالياً.": "No featured items available.",
  "سلة الطلب": "Your cart",
  "عدد الأصناف": "Items",
  "الإجمالي": "Total",
  "السلة فارغة.": "Your cart is empty.",
  "إتمام الطلب": "Checkout",
  "قد تُضاف رسوم توصيل حسب نوع الطلب — تظهر بعد اختيار طريقة الاستلام بالخطوة التالية.": "Delivery fees may apply depending on your order type. You will see them at checkout.",
  "الاسم": "Name",
  "رقم الجوال": "Mobile number",
  "نوع الطلب": "Order type",
  "الطاولة": "Table",
  "متاحة": "Available",
  "مشغولة": "Occupied",
  "لا توجد طاولات مفعّلة لهذا الفرع.": "No tables are currently available at this branch.",
  "عنوان التوصيل": "Delivery address",
  "تم تأكيد دفع الطلب.": "Your order payment has been confirmed.",
  "ملاحظات الطلب": "Order notes",
  "الفرع الرئيسي": "Main Branch",
  "TISH — الفرع الرئيسي": "TISH — Main Branch",
  "TISH - الفرع الرئيسي": "TISH - Main Branch",
  "TISH – الفرع الرئيسي": "TISH – Main Branch",
  "في انتظار الدفع": "Awaiting payment",
  "بانتظار الدفع": "Awaiting payment",
  "لم يتم تأكيد الدفع بعد.": "Payment has not been confirmed yet.",
  "تم تأكيد الدفع": "Payment confirmed",
  "تم الدفع": "Paid",
  "مدفوع": "Paid",
  "مدفوع جزئيًا": "Partially paid",
  "مدفوع جزئياً": "Partially paid",
  "تم الدفع جزئيًا": "Partially paid",
  "تم رفض الدفع": "Payment rejected",
  "تم رفض إثبات الدفع": "Payment proof rejected",
  "إثبات الدفع مرفوض": "Payment proof rejected",
  "فشل الدفع": "Payment failed",
  "تم استرداد المبلغ": "Refunded",
  "مسترد": "Refunded",
  "مسترد جزئيًا": "Partially refunded",
  "الدفع عند الاستلام": "Pay on pickup",
  "وصلنا طلبك!": "We received your order!",
  "وصلنا طلبك": "We received your order",
  "متابعة طلبي": "Track my order",
  "إغلاق": "Close",
  "طلب": "Order",
  "جاري تحضير طلبك": "Your order is being prepared",
  "طلبك جاهز!": "Your order is ready!",
  "فعّل التنبيهات لتسمع صوتًا عند تغيّر حالة الطلب.": "Enable alerts to hear a sound when your order status changes.",
  "ستصلك التنبيهات أثناء فتح صفحة الطلب.": "You will receive alerts while this order page is open.",
  "الوقت التقديري المتبقي: {time}": "Estimated time remaining: {time}",
  "بعض الأصناف ما زالت بانتظار التحضير": "Some items are still waiting to be prepared",
  "بانتظار تحديث وقت التحضير من المطعم": "Waiting for a preparation update from the restaurant",
  "انتهى الوقت التقديري، ننتظر تحديث المطبخ": "Estimated time elapsed. Waiting for a kitchen update.",
  "الوقت التقديري: حوالي {minutes} دقيقة (غير مضمون)": "Estimated time: about {minutes} minutes (not guaranteed)",
  "🔔 إشعارات الطلب مفعّلة": "🔔 Order notifications enabled",
  "🔔 تفعيل صوت وإشعارات الطلب": "🔔 Enable order sounds and notifications",
  "متابعة الطلب": "Track order",
  "يتم تحديث حالة الدفع تلقائيًا.": "Payment status updates automatically.",
  "إيقاف إشعارات الخلفية لهذا الطلب": "Disable background notifications for this order",
  "الصوت وإشعارات الخلفية مفعّلان على هذا الجهاز.": "Sound and background notifications are enabled on this device.",
  "الصوت مفعّل. إشعارات الخلفية تحتاج إعدادًا إضافيًا.": "Sound is enabled. Background notifications require additional setup.",
  "الصوت مفعّل. إشعارات الجهاز تحتاج إذن المتصفح.": "Sound is enabled. Device notifications require browser permission.",
  "الدفع عند الاستلام — لم يتم تحصيل المبلغ بعد.": "Pay on pickup — payment has not yet been collected.",
  "الحالة غير متاحة": "Status unavailable",
  "يرجى التواصل مع المطعم لتأكيد حالة الدفع.": "Please contact the restaurant to verify payment status.",
  "الوقت التقديري المتبقي": "Estimated time remaining",
  "حالة الدفع": "Payment status",
  "بانتظار التحقق": "Pending verification",
  "بانتظار التأكيد": "Awaiting confirmation",
  "إثبات الدفع قيد المراجعة لدى المطعم.": "Your payment proof is being reviewed by the restaurant.",
  "تفعيل صوت إشعارات الطلب": "Enable order notification sounds",
  "تفعيل إشعارات الطلب": "Enable order notifications",
  "عرض التفاصيل": "View details",
  "الكمية": "Quantity",
  "جاري تحضير الطلب": "Preparing your order",
  "المبلغ الإجمالي": "Total amount",
  "نوع السيارة": "Car type",
  "لون السيارة": "Car color",
  "مكان الوقوف": "Parking location",
  "مثال: Toyota Corolla": "Example: Toyota Corolla",
  "مثال: أسود": "Example: Black",
  "مثال: موقف 3": "Example: Parking spot 3",
  "بيانات السيارة": "Vehicle details",
  "طاولتك محددة تلقائيًا من QR": "Your table is automatically selected via QR",
  "الطلب من الطاولة يتطلب مسح QR": "Scan the table QR code to order",
  "يجب مسح QR الخاص بالطاولة لإتمام الطلب.": "Scan your table QR code to place an order.",
  "الطاولة لا تطابق رمز QR.": "The table does not match the QR code.",
  "رمز QR للطاولة غير صالح.": "Invalid table QR code.",
  "رمز الطاولة غير صالح أو تم إلغاؤه.": "This table QR code is invalid or has been revoked.",
  "طريقة الدفع": "Payment method",
  "جاري تحميل طرق الدفع...": "Loading payment methods...",
  "رقم مرجع العملية (اختياري)": "Transaction reference (optional)",
  "مثال: TX123456": "Example: TX123456",
  "إرفاق إشعار إثبات الدفع (صورة أو PDF)": "Attach payment proof (image or PDF)",
  "تأكيد وإرسال الطلب": "Confirm and place order",
  "الحساب": "Account",
  "اختر الحساب": "Select account",
  "البنك / الجهة": "Bank / provider",
  "اسم صاحب الحساب": "Account holder",
  "رقم الحساب": "Account number",
  "رقم المحفظة": "Wallet number",
  "تعليمات التحويل": "Transfer instructions",
  "اختر طاولة متاحة لطلب داخل المطعم.": "Choose an available table for dine-in.",
  "أدخل عنوان التوصيل.": "Please enter your delivery address.",
  "اختر طريقة الدفع قبل تأكيد الطلب.": "Select a payment method before placing your order.",
  "حجم إثبات الدفع يجب ألا يتجاوز 5 ميجابايت.": "Payment proof must not exceed 5 MB.",
  "جاري إرسال الطلب...": "Placing your order...",
  "انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.": "Your session expired. Refresh and try again.",
  "تعذر إرسال الطلب. حاول مرة أخرى.": "Couldn't place your order. Please try again.",
  "تم استلام طلبك بنجاح": "Your order was received successfully",
  "تم استلام الطلب": "Order received",
  "تم تأكيد الطلب": "Order confirmed",
  "قيد التحضير": "Preparing",
  "الطلب جاهز": "Order ready",
  "تم التسليم": "Delivered",
  "تم إلغاء الطلب": "Order cancelled",
  "جاري تحديث الحالة": "Updating status",
  "طلباتك": "Your orders",
  "الطلبات التي أنشأتها من هذا الجهاز.": "Orders placed from this device.",
  "اضغط لعرض التفاصيل وتتبع الحالة": "Tap for details and live tracking",
  "لا توجد طلبات بعد": "No orders yet",
  "بعد إرسال أول طلب سيظهر هنا تلقائيًا.": "Your first order will appear here after checkout.",
  "ابدأ طلبًا جديدًا": "Start an order",
  "تم تحديث الطلب": "Order updated",
  "تتبع": "Track",
  "الموقع": "Website",
  "تحميل التطبيق": "Install app",
  "تفاصيل الطلب": "Order details",
  "مراحل الطلب": "Order progress",
  "تم الاستلام": "Received",
  "تم التأكيد": "Confirmed",
  "جاهز": "Ready",
  "ملخص الطلب": "Order summary",
  "التحديث تلقائيًا": "Updates automatically",
  "الكمية:": "Quantity:",
  "طلب جديد": "New order",
  "كل طلباتي": "All my orders",
  "عرض الفاتورة": "View invoice",
  "جاري تجهيز طلبك": "Your order is being prepared",
  "تم إكمال الطلب": "Order completed",
  "طلبك جاهز": "Your order is ready",
  "طلبك جاهز 🎉": "Your order is ready 🎉",
  "طلبك جاهز الآن للاستلام.": "Your order is ready for pickup.",
  "إثبات الدفع قيد المراجعة — سنحدّث الحالة فور اعتماد العملية.": "Your payment proof is being reviewed.",
  "تعذر تحديث الحالة مؤقتًا": "Unable to refresh order status right now",
  "من أي فرع بدك تطلب؟": "Which branch would you like?",
  "اختر الفرع المناسب إلك، وبعدها تصفّح المنيو وابدأ طلبك.": "Choose a branch to browse the menu and place your order.",
  "ابحث عن فرع أو منطقة": "Search branch or area",
  "لا يوجد فرع مطابق للبحث.": "No matching branch found.",
  "لا توجد فروع متاحة حاليًا. حاول مجدداً لاحقًا.": "No branches are available right now. Please try again later.",
  "حذف": "Remove",
  "إضافة المزيد": "Add more",
  "رقم الطلب": "Order number",
  "تعذر تحميل طرق الدفع": "Could not load payment methods",
  "لا توجد طرق دفع مفعلة.": "No payment methods are available.",
  "اختر طريقة الاستلام": "Choose service type",
  "داخل المطعم": "Dine-in",
  "سفري": "Takeaway",
  "توصيل": "Delivery",
  "قطعة واحدة": "1 item",
  "قطعتان": "2 items",
  "صنف": "item",
  "أصناف": "items",
  "العرض": "Offer",
  "كل اللي بتحبه": "Everything you love",
  "للتوصيل": "For delivery",
  "للسفري": "Takeaway",
  "لحجز الطاولة": "Reserve a table",
  "اختر فرعك": "Choose your branch",
  "العودة": "Back",
  "طلباتي ←": "My orders ←",
  "التنقل": "Navigation",
  "اختار صنفك المفضل واطلبه مباشرة من المنيو.": "Pick your favorite treat and order from the menu.",
  "صنف متاح": "item available",
  "عرض": "View",
  "عرض {name}": "View {name}",
  "صنف": "Item",
  "حساب": "Account",
  "استلام من الباب": "Curbside pickup",
  "طلب مطعم": "Restaurant order",
  "بطاقة": "Card",
  "نقداً": "Cash",
  "نقدي": "Cash",
  "نقد": "Cash",
  "تحويل بنكي": "Bank transfer",
  "محفظة إلكترونية": "Electronic wallet",
  "تعذر تحميل طرق الدفع. حدّث الصفحة أو راجع إعدادات الفرع.": "Unable to load payment methods. Refresh the page or contact the branch.",
  "حجم إثبات الدفع أكبر من المسموح على السيرفر. جرّب ملفاً أصغر.": "Payment proof exceeds the server limit. Try a smaller file.",
  "تعذر قراءة نتيجة الطلب. أعد المحاولة بنفس الصفحة.": "Could not read the order confirmation. Please retry from this page.",
  "الوقت المتوقع لتجهيز طلبك: حوالي {minutes} دقيقة": "Estimated preparation time: about {minutes} minutes",
  "رسالة من الفرع: {message}": "Message from the branch: {message}",
  "طلب #{number}": "Order #{number}",
  "اختر من {min} إلى {max}": "Choose {min} to {max}",
  "اختر حتى {max}": "Choose up to {max}",
  "مجموعة \"{name}\" تتطلب اختيار {min} على الأقل.": "Choose at least {min} option(s) in \"{name}\".",
  "مجموعة \"{name}\" تسمح بحد أقصى {max}.": "Choose no more than {max} option(s) in \"{name}\".",
  "تم إلغاء الطلب. تواصل مع الفرع إذا احتجت للمساعدة.": "Your order was cancelled. Contact the branch if you need help.",
  "تم تسليم طلبك. شكراً لاختيارك لنا.": "Your order was delivered. Thank you for choosing us.",
  "طلبك جاهز الآن للاستلام. نتمنى لك وجبة شهية!": "Your order is ready for pickup. Enjoy!",
  "تم استلام طلبك وبدأنا بمراجعته.": "We received your order and are reviewing it.",
  "تم قبول طلبك ويجري تحضيره.": "Your order is confirmed and being prepared.",
  "طلبك قيد التحضير الآن.": "Your order is now being prepared.",
  "تم استلام الطلب.": "Order received.",
  "عدد {count} أصناف": "{count} items",
  "تم استلام طلبك": "Your order was received",
  "تم قبول الطلب": "Order confirmed",
  "طلبك قيد التحضير": "Your order is being prepared",
  "فريقنا يجهز طلبك الآن.": "Our team is preparing your order now.",
  "تم قبول طلبك وسيدخل التحضير الآن.": "Your order was accepted and will be prepared shortly.",
  "وصل طلبك للفرع وهو بانتظار القبول.": "Your order has reached the branch and is awaiting confirmation.",
  "اطلب أسرع من تطبيق": "Order faster with the app",
  "اختار صنفك المفضل واطلبه مباشرة من المنيو.": "Choose your favorite item and order from the menu."


};
 // Additional shared public UI translations, including current meal windows and PWA help.
 Object.assign(dictionary, {
  "الأكثر طلبًا": "Most popular",
  "الأكثر طلبا": "Most popular",
  "ثبّت المنيو": "Install menu",
  "ثبت المنيو": "Install menu",
  "تثبيت المنيو": "Install menu",
  "تثبيت تطبيق المنيو": "Install the menu app",
  "إضافة المنيو للشاشة الرئيسية": "Add the menu to your home screen",
  "افتح المنيو عبر اتصال HTTPS آمن، ثم استخدم خيار تثبيت التطبيق من المتصفح.": "Open the menu over a secure HTTPS connection, then use your browser’s install option.",
  "افتح قائمة المشاركة في المتصفح (رمز المشاركة أو قائمة الخيارات).": "Open your browser’s Share menu.",
  "اختر «إضافة إلى الشاشة الرئيسية» (Add to Home Screen).": "Select Add to Home Screen.",
  "اضغط «إضافة» لتظهر أيقونة المنيو على جهازك.": "Tap Add to place the menu icon on your device.",
  "افتح قائمة متصفح Samsung Internet.": "Open the Samsung Internet menu.",
  "اختر «إضافة الصفحة إلى» ثم «الشاشة الرئيسية» أو «تثبيت التطبيق» إذا كان متاحًا.": "Select Add page to, then Home screen or Install app if available.",
  "افتح قائمة المتصفح (⋮).": "Open your browser menu (⋮).",
  "اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية» حسب المتصفح.": "Choose Install app or Add to Home Screen, depending on your browser.",
  "من قائمة File في Safari اختر Add to Dock (على الإصدارات التي تدعمها).": "In Safari, choose File > Add to Dock (on supported versions).",
  "أكّد إضافة التطبيق إلى Dock.": "Confirm adding the app to your Dock.",
  "قد لا يدعم متصفحك تثبيت الموقع كتطبيق مستقل على الكمبيوتر.": "Your browser may not support installing websites as desktop apps.",
  "يمكنك حفظ المنيو كاختصار أو فتحه في Chrome أو Edge لتجربة خيار التثبيت.": "Save the menu as a shortcut or open it in Chrome or Edge to install.",
  "افتح قائمة المتصفح (⋮ أو …) أو خيار التثبيت بجوار شريط العنوان.": "Open the browser menu (⋮ or …), or the install option next to the address bar.",
  "اختر «تثبيت التطبيق» أو «Create shortcut» بحسب المتصفح.": "Choose Install app or Create shortcut, depending on your browser.",
  "الأصناف": "Items",
  "أزرار المنيو": "Menu controls",
  "إعلانات المنيو": "Menu promotions",
  "عرض كل الأصناف": "View all items",
  "وجبات اليوم": "Today’s meals",
  "وجبات الفطور": "Breakfast",
  "وجبات الغداء": "Lunch",
  "لا توجد وجبات متاحة في هذه الفترة حاليًا.": "No meals are available during this period right now.",
  "انتهى وقت هذه الوجبة. اختر وجبة أخرى.": "This meal period has ended. Choose another meal.",
  "انتهى وقت الوجبة — احذف الصنف للمتابعة": "Meal period ended — remove this item to continue",
  "انتهى وقت الوجبة": "Meal period ended",
  "انتهى وقت إحدى الوجبات. لا تحوّل المبلغ الآن. عد إلى السلة واحذف الصنف غير المتاح.": "A meal period has ended. Do not transfer payment now. Return to your cart and remove the unavailable item.",
  "انتهى وقت إحدى الوجبات. لا تحوّل المبلغ الآن. راجع السلة قبل المتابعة.": "A meal period has ended. Do not transfer payment now. Review your cart before continuing.",
  "اختر حساب الدفع أو التحويل قبل تأكيد الطلب.": "Select a payment or transfer account before placing your order.",
  "المكونات": "Ingredients",
  "تقليل الكمية": "Decrease quantity",
  "زيادة الكمية": "Increase quantity",
  "كمّل وجبتك": "Complete your meal",
  "خلّي وجبتك كاملة!": "Make it a meal!",
  "اختار بطاطا أو مشروب أو صوص... حسب ذوقك.": "Choose fries, a drink or a sauce to make it yours.",
  "بطاطا ومقبلات": "Fries and sides",
  "مشروبات منعشة": "Refreshing drinks",
  "صوصات وتغميسات": "Sauces and dips",
  "اكتشف المزيد": "Discover more",
  "اختيار تفاصيل": "Choose options",
  "أضف": "Add",
  "عرض الصنف": "View item",
  "إيقاف التبديل التلقائي": "Pause autoplay",
  "عرض أقل": "Show less",
  "اختر التفاصيل": "Choose options",
  "ما عندك أصناف بالمفضلة بعد": "No favorites yet",
  "تمت الإزالة من المفضلة": "Removed from favorites",
  "قطع": "items",
  "طاولة": "Table",
  "طاولتك": "Your table",
  "الطاولة محددة تلقائياً": "Table automatically selected",
  "طلبك": "Your order",
  "جاري التحديث": "Updating",
  "تفعيل صوت وإشعارات الطلب": "Enable order sounds and notifications",
  "حفظ الفاتورة كصورة": "Save invoice as image",
  "حالة طلبك": "Your order status",
  "افتح بطاقة متابعة الطلب": "Open order tracking",
  "هذا الجهاز لا يدعم إشعارات الخلفية على هذه الصفحة.": "This device does not support background notifications on this page.",
  "تعذر قراءة إعدادات إشعارات الخلفية.": "Could not load background notification settings.",
  "إشعارات الخلفية تحتاج إعداد مفاتيح VAPID على السيرفر.": "Background notifications require server configuration.",
  "إشعارات النظام غير مدعومة على هذا الجهاز.": "System notifications are not supported on this device.",
  "لم يتم منح إذن الإشعارات.": "Notification permission was not granted.",
  "تعذر حفظ اشتراك الجهاز في السيرفر.": "Could not save this device’s notification subscription.",
  "اعتمدنا طلبك!": "Your order is confirmed!",
  "طلبك على السماش": "Your order is on the grill",
  "طلبك جاهز، بالعافية!": "Your order is ready. Enjoy!",
  "بالعافية عليك!": "Enjoy your meal!",
  "تحديث طلبك": "Order update",
  "إشعارات الطلب مفعّلة": "Order notifications enabled",
  "تعذر إلغاء اشتراك هذا الطلب.": "Could not disable notifications for this order.",
  "تم تحديث حالة طلبك.": "Your order status has been updated.",
  "تم استلام جزء من المبلغ.": "Partial payment received.",
  "مسترجع جزئيًا": "Partially refunded",
  "تم استرجاع جزء من المبلغ.": "Part of your payment has been refunded.",
  "مسترجع": "Refunded",
  "تم استرجاع المبلغ.": "Your payment has been refunded.",
  "جارٍ تجهيز صورة الفاتورة...": "Preparing invoice image...",
  "فاتورة": "Invoice",
  "تعذر إنشاء صورة الفاتورة. يمكنك استخدام زر عرض الفاتورة.": "Could not create the invoice image. Use View invoice instead.",
  "بانتظار التحقق من الدفع": "Awaiting payment verification",
  "مستردة جزئيًا": "Partially refunded",
  "مستردة بالكامل": "Fully refunded",
  "فاتورة رقم": "Invoice no.",
  "إجراءات الفاتورة": "Invoice actions",
  "تتبع الطلب": "Track order",
  "طباعة الفاتورة": "Print invoice",
  "فاتورة طلب TISH": "TISH order invoice",
  "تاريخ ووقت إصدار الفاتورة": "Invoice issue date and time",
  "تاريخ الإصدار": "Issue date",
  "الوقت": "Time",
  "الدفع عند الاستلام — لم يتم التحصيل بعد": "Pay on pickup — payment not yet collected",
  "الصنف": "Item",
  "سعر الوحدة والكمية": "Unit price and quantity",
  "لا توجد أصناف في الفاتورة.": "No invoice items.",
  "ملخص الفاتورة": "Invoice summary",
  "المجموع الفرعي": "Subtotal",
  "الخصم": "Discount",
  "الضريبة": "Tax",
  "التحصيل قبل الاسترداد": "Collected before refunds",
  "المسترد": "Refunded",
  "صافي المدفوع": "Net paid",
  "الفاتورة ملغاة": "Invoice cancelled",
  "التنقل بين الصفحات": "Page navigation",
  "الفرع غير متاح للطلب حالياً.": "This branch is currently unavailable for ordering.",
  "الطلب من المنيو متوقف مؤقتاً.": "Online ordering is temporarily paused.",
  "رمز الطلب مرتبط بفرع آخر. افتح إتمام الطلب من هذا الفرع وحاول مجدداً.": "This order token belongs to another branch. Open checkout from this branch and try again.",
  "طريقة الدفع المحددة غير متاحة في هذا الفرع.": "The selected payment method is unavailable at this branch.",
  "طريقة الدفع هذه معطلة في إعدادات طلب العميل.": "This payment method is disabled for online orders.",
  "اختر حساب التحويل الصحيح لهذا الفرع.": "Select the correct transfer account for this branch.",
  "أدخل رقم مرجع عملية الدفع.": "Enter the payment reference number.",
  "ارفع صورة أو ملف إثبات التحويل قبل تأكيد الطلب.": "Upload a photo or file of your transfer proof before placing the order.",
  "تعذر حفظ إثبات الدفع. حاول مرة أخرى أو اختر الدفع عند الاستلام.": "Could not save the payment proof. Try again or choose pay on pickup if available.",
  "الحد الأقصى لكل صنف بنفس الخيارات هو 50. عدّل الكمية وحاول مجدداً.": "The maximum quantity per item with the same options is 50. Adjust the quantity and try again.",
  "أضف صنفاً واحداً على الأقل إلى السلة.": "Add at least one item to your cart.",
  "أحد الأصناف لم يعد متاحاً في منيو هذا الفرع. حدّث الصفحة وحاول مرة أخرى.": "An item is no longer available at this branch. Refresh and try again.",
  "نوع الطلب المحدد غير متاح حالياً.": "The selected order type is currently unavailable.",
  "برجر لحم": "Beef Burgers",
  "برجر دجاج": "Chicken Burgers",
  "برجر نباتي": "Veggie Burgers",
  "الفرايز": "Fries",
  "الفطور": "Breakfast",
  "الغداء": "Lunch",
  "المقبلات": "Sides",
  "الصوصات": "Sauces",
  "المشروبات الباردة": "Cold Drinks"
});
 const normalizeKey = value => String(value).normalize('NFKC').replace(/[\u064b-\u065f\u0670\u0640]/g,'').replace(/\s+/g,' ').trim();
 const normalizedDictionary = new Map(Object.entries(dictionary).map(([key,value])=>[normalizeKey(key),value]));
 const lookup = value => Object.prototype.hasOwnProperty.call(dictionary,value) ? dictionary[value] : normalizedDictionary.get(normalizeKey(value));
 const allowed = ['ar','en'];
 const cookieName = 'dahab_menu_lang';
 const isEnglish = document.documentElement.lang === 'en';
 const language = isEnglish ? 'en' : 'ar';
 const translate = (value) => {
   const input = String(value ?? '');
   if (language !== 'en') return input;
   const trimmed = input.trim();
   if (!trimmed) return input;
   let translated = lookup(trimmed);
   const key = normalizeKey(trimmed);
   if (!translated && /[:：]$/.test(trimmed)) {
     const base = trimmed.slice(0,-1).trim();
     if (lookup(base)) translated = lookup(base) + ':';
   }
   if (!translated) {
     const count = key.match(/^(?:عدد )?(\d+)\s+(?:أصناف|صنف متاح|صنف|قطع)$/);
     if (count) translated = count[1] + (count[1] === '1' ? ' item' : ' items');
   }
   if (!translated) {
     const price = key.match(/^(أقل|أكثر) من (\d+)(.*)$/);
     if (price) translated = (price[1] === 'أقل' ? 'Under ' : 'Over ') + price[2] + price[3];
   }
   if (!translated) {
     const meal = key.match(/^وجبات (الفطور|الغداء) متاحة فقط من (\d{2}:\d{2}) إلى (\d{2}:\d{2}) بتوقيت غزة\. راجع السلة قبل الدفع\.$/);
     if (meal) translated = (meal[1] === 'الفطور' ? 'Breakfast' : 'Lunch') + ' is available only from ' + meal[2] + ' to ' + meal[3] + ' Gaza time. Review your cart before paying.';
     const closed = key.match(/^وجبات (الفطور|الغداء) غير متاحة حاليا\.$/);
     if (closed) translated = (closed[1] === 'الفطور' ? 'Breakfast' : 'Lunch') + ' is currently unavailable.';
   }
   return translated ? input.replace(trimmed, () => translated) : input;
 };
 const translateAttribute = (value) => {
   const original = String(value ?? '');
   const translated = translate(original);
   if (language !== 'en' || translated !== original) return translated;
   const prefixes = ['إضافة للمفضلة','أضف للمفضلة','إزالة من المفضلة','عرض الصنف','عرض','إضافة للسلة','اختر الخيارات','غير متوفر','تقليل كمية','زيادة كمية','طاولتك','الطاولة محددة تلقائيا'];
   for (const prefix of prefixes) {
     const normalized = normalizeKey(prefix);
     const match = original.trim().match(new RegExp('^' + normalized.split('').map(c => c + '[\\u064b-\\u065f\\u0670\\u0640]*').join('') + '([: ]+)(.*)$','u'));
     if (match) return lookup(prefix) + (match[1].includes(':') ? ': ' : ' ') + match[2];
   }
   return original;
 };
 const format = (key, values = {}) => {
   const template = translate(key);
   return template.replace(/\{([a-zA-Z_]+)\}/g, (match, name) =>
     Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match);
 };
 window.CustomerMenuI18n = {lang:language, isEnglish, t:translate, format, dictionary};
 function updateText(root) {
   if (language !== 'en' || !root || !document.createTreeWalker) return;
   const visitor = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
   let item;
   while ((item = visitor.nextNode())) {
     const parent = item.parentElement;
     if (!parent || ['SCRIPT','STYLE','NOSCRIPT','TEXTAREA'].includes(parent.tagName)) continue;
     const next = translate(item.nodeValue);
     if (next !== item.nodeValue) item.nodeValue = next;
   }
   if (root.querySelectorAll) {
     const elements = [root, ...root.querySelectorAll('[placeholder],[aria-label],[title]')];
     for (const el of elements) {
       if (!el.getAttribute) continue;
       for (const name of ['placeholder','aria-label','title']) {
         if (el.hasAttribute(name)) {
           const original = el.getAttribute(name);
           const value = translateAttribute(original);
           if (value !== original) el.setAttribute(name,value);
         }
       }
     }
   }
 }
 function applyLocale() {
   document.documentElement.lang = language;
   document.documentElement.dir = language === 'en' ? 'ltr' : 'rtl';
   document.cookie = cookieName + '=' + language + '; Path=/; Max-Age=31536000; SameSite=Lax';
   if (language === 'en') {
     document.title = document.title.replace(/^(المنيو|المفضلة|اختر فرعك|سلة الطلب|إتمام الطلب|طلباتي)\s*([-—])/, (_,key,sep) => translate(key) + ' ' + sep);
     document.title = document.title.replace(/^تتبع\s+/, 'Track ');
     updateText(document.body);
   }
   const host = document.querySelector('[data-cm-language-host]')
     || document.querySelector('body.reference-home .tish-drive-hero-actions')
     || document.querySelector('.reference-actions,.page-head,.branch-brand') || document.body;
   const button = document.createElement('button');
   button.type = 'button';
   button.className = 'cm-language-switch';
   button.dataset.menuLanguageToggle = 'true';
   button.setAttribute('aria-label', language === 'en' ? 'Switch to Arabic' : 'Switch to English');
   button.setAttribute('title', language === 'en' ? 'Switch to Arabic' : 'Switch to English');
   button.textContent = language === 'en' ? 'العربية' : 'English';
   if (host === document.body) button.classList.add('cm-language-floating');
   host.appendChild(button);
   button.addEventListener('click', () => {
     const next = language === 'en' ? 'ar' : 'en';
     document.cookie = cookieName + '=' + next + '; Path=/; Max-Age=31536000; SameSite=Lax';
     const url = new URL(window.location.href);
     url.searchParams.set('lang',next);
     window.location.assign(url.toString());
   });
   if (language === 'en' && typeof MutationObserver !== 'undefined') {
     let pending = false;
     const observer = new MutationObserver(() => {
       if (pending) return;
       pending = true;
       requestAnimationFrame(() => {
         pending = false;
         updateText(document.body);
       });
     });
     observer.observe(document.body, {subtree:true,childList:true,characterData:true});
   }
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',applyLocale,{once:true});
 else applyLocale();
})();